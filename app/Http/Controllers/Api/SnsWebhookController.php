<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Suppression;
use App\Services\Alerting\AlertDispatcherService;
use App\Services\Deliverability\RateLimiterAndProtectionService;
use App\Support\UrlSafetyValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SnsWebhookController extends Controller
{
    /**
     * Handles incoming AWS SNS notifications for SES events (Delivery, Bounce, Complaint).
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'Invalid JSON payload'], 400);
        }

        // Verify cryptographic AWS SNS X.509 signature in production (or whenever enabled)
        if ((bool) config('services.ses.verify_sns_signature', true)) {
            if (! $this->verifySnsSignature($payload)) {
                Log::warning('Rejected AWS SNS webhook with invalid or untrusted X.509 signature.');

                return response()->json(['error' => 'Invalid SNS signature'], 403);
            }
        }

        $type = (string) ($payload['Type'] ?? '');

        // 1. Handle AWS SNS SubscriptionConfirmation safely (strictly AWS SNS HTTPS URLs only)
        if ($type === 'SubscriptionConfirmation') {
            $subscribeUrl = (string) ($payload['SubscribeURL'] ?? '');
            if ($subscribeUrl === '' || ! UrlSafetyValidator::isValidAwsSnsUrl($subscribeUrl)) {
                Log::warning('Blocked non-AWS SubscribeURL on SNS webhook', ['url' => $subscribeUrl]);

                return response()->json(['error' => 'Untrusted SubscribeURL'], 403);
            }

            try {
                Http::timeout(5)->get($subscribeUrl);
                Log::info('Confirmed AWS SNS subscription via SubscribeURL.');
            } catch (Throwable $e) {
                Log::error('Failed confirming AWS SNS SubscribeURL: '.$e->getMessage());

                return response()->json(['error' => 'Confirmation request failed'], 502);
            }

            return response()->json(['status' => 'confirmed']);
        }

        // 2. Deduplicate SNS delivery retries by MessageId
        $snsMessageId = (string) ($payload['MessageId'] ?? '');
        if ($snsMessageId !== '' && ! Cache::add("spiggle_sns_msg:{$snsMessageId}", true, 86400)) {
            return response()->json(['status' => 'duplicate_ignored']);
        }

        // 3. Process SES Notification payload
        if ($type === 'Notification' && ! empty($payload['Message'])) {
            $messageRaw = $payload['Message'];
            $messageData = is_array($messageRaw) ? $messageRaw : json_decode((string) $messageRaw, true);
            if (! is_array($messageData)) {
                return response()->json(['error' => 'Invalid SNS Message content'], 400);
            }

            $this->processSesEvent($messageData);
        }

        return response()->json(['status' => 'processed']);
    }

    private function processSesEvent(array $event): void
    {
        $eventType = strtolower((string) ($event['eventType'] ?? $event['notificationType'] ?? ''));
        if ($eventType === '') {
            return;
        }

        $mail = is_array($event['mail'] ?? null) ? $event['mail'] : [];
        $sesMessageId = ! empty($mail['messageId']) ? (string) $mail['messageId'] : null;

        $messageRecord = $sesMessageId
            ? Message::with(['contact.newsletter', 'campaign'])->where('ses_message_id', $sesMessageId)->first()
            : null;

        DB::transaction(function () use ($eventType, $event, $messageRecord): void {
            match ($eventType) {
                'delivery' => $this->handleDelivery($event, $messageRecord),
                'bounce' => $this->handleBounce($event, $messageRecord),
                'complaint' => $this->handleComplaint($event, $messageRecord),
                default => null,
            };
        });
    }

    private function handleDelivery(array $event, ?Message $messageRecord): void
    {
        if ($messageRecord) {
            if ($messageRecord->status !== 'delivered') {
                $messageRecord->update([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);
                if ($messageRecord->campaign) {
                    $messageRecord->campaign->increment('delivered_count');
                }
            }

            return;
        }

        $recipients = (array) ($event['delivery']['recipients'] ?? []);
        foreach ($recipients as $rawEmail) {
            $email = strtolower(trim((string) $rawEmail));
            if ($email === '') {
                continue;
            }

            $msg = Message::with('campaign')
                ->whereHas('contact', fn ($q) => $q->where('email', $email))
                ->latest('created_at')
                ->first();

            if ($msg && $msg->status !== 'delivered') {
                $msg->update([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);
                if ($msg->campaign) {
                    $msg->campaign->increment('delivered_count');
                }
            }
        }
    }

    private function handleBounce(array $event, ?Message $messageRecord): void
    {
        $bounce = is_array($event['bounce'] ?? null) ? $event['bounce'] : [];
        $bounceType = (string) ($bounce['bounceType'] ?? 'Permanent');
        $isSoftBounce = strcasecmp($bounceType, 'Transient') === 0;
        $bouncedRecipients = (array) ($bounce['bouncedRecipients'] ?? []);
        $diagnostic = (string) ($bouncedRecipients[0]['diagnosticCode'] ?? $bounce['bounceSubType'] ?? 'Bounce');

        if ($messageRecord) {
            $wasAlreadyBounced = $messageRecord->status === 'bounced';
            $messageRecord->update([
                'status' => 'bounced',
                'bounced_at' => now(),
                'error' => $diagnostic,
            ]);

            if (! $wasAlreadyBounced && $messageRecord->campaign && ! $isSoftBounce) {
                $messageRecord->campaign->increment('bounce_count');
                $this->evaluateCircuitBreaker($messageRecord->campaign->fresh());
            }
        }

        // Process all bounced recipient addresses (covers both campaign & transactional emails!)
        $emails = [];
        foreach ($bouncedRecipients as $rec) {
            $addr = strtolower(trim((string) ($rec['emailAddress'] ?? '')));
            if ($addr !== '') {
                $emails[] = $addr;
            }
        }
        if ($emails === [] && $messageRecord?->contact?->email) {
            $emails[] = strtolower(trim($messageRecord->contact->email));
        }

        foreach (array_unique($emails) as $email) {
            // If no ses_message_id match existed, correlate latest message for this contact
            if (! $messageRecord) {
                $fallbackMsg = Message::with('campaign')
                    ->whereHas('contact', fn ($q) => $q->where('email', $email))
                    ->latest('created_at')
                    ->first();

                if ($fallbackMsg && $fallbackMsg->status !== 'bounced') {
                    $fallbackMsg->update([
                        'status' => 'bounced',
                        'bounced_at' => now(),
                        'error' => $diagnostic,
                    ]);
                    if ($fallbackMsg->campaign && ! $isSoftBounce) {
                        $fallbackMsg->campaign->increment('bounce_count');
                        $this->evaluateCircuitBreaker($fallbackMsg->campaign->fresh());
                    }
                }
            }

            $contacts = Contact::with('newsletter')->where('email', $email)->get();
            if ($contacts->isEmpty() && ! $isSoftBounce) {
                Suppression::firstOrCreate(
                    ['email_hash' => hash_hmac('sha256', $email, (string) config('app.key'))],
                    ['reason' => 'bounce']
                );

                continue;
            }

            foreach ($contacts as $contact) {
                if ($isSoftBounce) {
                    $newSoftCount = ((int) $contact->soft_bounce_count) + 1;
                    if ($newSoftCount >= 3) {
                        $contact->update([
                            'soft_bounce_count' => $newSoftCount,
                            'status' => 'bounced',
                        ]);
                        $this->suppressEmail($email, 'bounce', $contact->newsletter?->account_id);
                    } else {
                        $contact->update(['soft_bounce_count' => $newSoftCount]);
                    }
                } else {
                    $contact->update(['status' => 'bounced']);
                    $this->suppressEmail($email, 'bounce', $contact->newsletter?->account_id);
                }
            }
        }
    }

    private function handleComplaint(array $event, ?Message $messageRecord): void
    {
        $complaint = is_array($event['complaint'] ?? null) ? $event['complaint'] : [];
        $complainedRecipients = (array) ($complaint['complainedRecipients'] ?? []);
        $feedbackType = (string) ($complaint['complaintFeedbackType'] ?? 'Abuse report');

        if ($messageRecord) {
            $wasAlreadyComplained = $messageRecord->status === 'complained';
            $messageRecord->update([
                'status' => 'complained',
                'complained_at' => now(),
                'error' => $feedbackType,
            ]);

            if (! $wasAlreadyComplained && $messageRecord->campaign) {
                $messageRecord->campaign->increment('complaint_count');
                $this->evaluateCircuitBreaker($messageRecord->campaign->fresh());
            }
        }

        $emails = [];
        foreach ($complainedRecipients as $rec) {
            $addr = strtolower(trim((string) ($rec['emailAddress'] ?? '')));
            if ($addr !== '') {
                $emails[] = $addr;
            }
        }
        if ($emails === [] && $messageRecord?->contact?->email) {
            $emails[] = strtolower(trim($messageRecord->contact->email));
        }

        foreach (array_unique($emails) as $email) {
            if (! $messageRecord) {
                $fallbackMsg = Message::with('campaign')
                    ->whereHas('contact', fn ($q) => $q->where('email', $email))
                    ->latest('created_at')
                    ->first();

                if ($fallbackMsg && $fallbackMsg->status !== 'complained') {
                    $fallbackMsg->update([
                        'status' => 'complained',
                        'complained_at' => now(),
                        'error' => $feedbackType,
                    ]);
                    if ($fallbackMsg->campaign) {
                        $fallbackMsg->campaign->increment('complaint_count');
                        $this->evaluateCircuitBreaker($fallbackMsg->campaign->fresh());
                    }
                }
            }

            $contacts = Contact::with('newsletter')->where('email', $email)->get();
            if ($contacts->isEmpty()) {
                $this->suppressEmail($email, 'complaint', null);

                continue;
            }

            foreach ($contacts as $contact) {
                $contact->update(['status' => 'complained']);
                $this->suppressEmail($email, 'complaint', $contact->newsletter?->account_id);
            }
        }
    }

    private function suppressEmail(string $email, string $reason, ?string $accountId): void
    {
        $hash = hash_hmac('sha256', strtolower(trim($email)), (string) config('app.key'));

        Suppression::firstOrCreate(
            [
                'account_id' => $accountId,
                'email_hash' => $hash,
            ],
            [
                'reason' => $reason,
            ]
        );
    }

    private function evaluateCircuitBreaker(?Campaign $campaign): void
    {
        if (! $campaign) {
            return;
        }

        $evaluation = RateLimiterAndProtectionService::evaluateSafety(
            sent: (int) $campaign->sent_count,
            bounced: (int) $campaign->bounce_count,
            complained: (int) $campaign->complaint_count
        );

        if ($evaluation['action'] !== 'pause' && $evaluation['action'] !== 'block') {
            return;
        }

        $reason = sprintf(
            'Auto-paused: %s rate reached %.2f%% (threshold %.2f%%)',
            ucfirst((string) $evaluation['metric']),
            $evaluation['rate'] * 100,
            $evaluation['limit'] * 100
        );

        $campaign->update([
            'status' => 'paused',
            'pause_reason' => $reason,
            'pause_code' => $evaluation['action'],
        ]);

        $accountId = $campaign->newsletter?->account_id;

        if ($evaluation['action'] === 'block' && $accountId) {
            Account::where('id', $accountId)->update([
                'status' => 'paused',
                'paused_by' => 'ses_feedback',
                'paused_at' => now(),
                'pause_reason' => "Automated SES circuit-breaker: {$reason}",
            ]);
        }

        try {
            app(AlertDispatcherService::class)->dispatch([
                'title' => "SES Feedback Circuit Breaker: {$evaluation['metric']} threshold breached",
                'message' => $reason,
                'priority' => $evaluation['action'] === 'block' ? 'critical' : 'high',
                'action' => 'circuit_breaker.tripped',
                'account_id' => $accountId,
                'tags' => ['ses-webhook', 'circuit-breaker', (string) $evaluation['metric']],
                'context' => [
                    'campaign_id' => $campaign->id,
                    'subject' => $campaign->subject,
                    'rate' => $evaluation['rate'],
                    'limit' => $evaluation['limit'],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('SNS circuit-breaker alert dispatch failed: '.$e->getMessage());
        }
    }

    /**
     * Cryptographically validates the AWS SNS X.509 signature per AWS SNS specification.
     */
    private function verifySnsSignature(array $payload): bool
    {
        $certUrl = (string) ($payload['SigningCertURL'] ?? $payload['SigningCertUrl'] ?? '');
        $signatureBase64 = (string) ($payload['Signature'] ?? '');
        $sigVersion = (string) ($payload['SignatureVersion'] ?? '1');

        if ($certUrl === '' || $signatureBase64 === '' || ! UrlSafetyValidator::isValidAwsSnsUrl($certUrl)) {
            return false;
        }

        if (! in_array($sigVersion, ['1', '2'], true)) {
            return false;
        }

        $signature = base64_decode($signatureBase64, true);
        if ($signature === false) {
            return false;
        }

        $canonical = $this->buildCanonicalString($payload);
        if ($canonical === '') {
            return false;
        }

        try {
            $pem = Cache::remember('aws_sns_cert:'.sha1($certUrl), 3600, static function () use ($certUrl): string {
                $res = Http::timeout(5)->get($certUrl);

                return $res->successful() ? $res->body() : '';
            });

            if ($pem === '') {
                return false;
            }

            $pubKey = openssl_pkey_get_public($pem);
            if ($pubKey === false) {
                return false;
            }

            $algo = $sigVersion === '2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;

            return openssl_verify($canonical, $signature, $pubKey, $algo) === 1;
        } catch (Throwable $e) {
            Log::error('SNS signature verification error: '.$e->getMessage());

            return false;
        }
    }

    private function buildCanonicalString(array $payload): string
    {
        $type = (string) ($payload['Type'] ?? '');
        $keys = $type === 'Notification'
            ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type']
            : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];

        $lines = '';
        foreach ($keys as $key) {
            if (isset($payload[$key]) && $payload[$key] !== null && $payload[$key] !== '') {
                $lines .= $key."\n".$payload[$key]."\n";
            }
        }

        return $lines;
    }
}
