<?php

namespace App\Jobs;

use App\Models\Account;
use App\Services\Alerting\AlertDispatcherService;
use App\Services\Campaign\ShortcodeParserService;
use App\Services\Deliverability\RateLimiterAndProtectionService;
use App\Services\Deliverability\TrackingTokenService;
use App\Services\Deliverability\UnsubscribeHeaderService;
use App\Services\Email\EmailProviderInterface;
use App\Support\HtmlSanitizer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendCampaignBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $campaignId;
    public int $batchSize;

    public function __construct(string $campaignId, int $batchSize = 50)
    {
        $this->campaignId = $campaignId;
        $this->batchSize = $batchSize;
    }

    public function handle(
        EmailProviderInterface $emailProvider,
        ShortcodeParserService $shortcodeParser
    ): void {
        $lock = Cache::lock("spiggle_campaign_send:{$this->campaignId}", 120);
        if (! $lock->get()) {
            Log::info("Campaign {$this->campaignId} batch already running under lock; skipping concurrent worker.");

            return;
        }

        try {
            $this->dispatchLockedBatch($emailProvider, $shortcodeParser);
        } finally {
            $lock->release();
        }
    }

    private function dispatchLockedBatch(
        EmailProviderInterface $emailProvider,
        ShortcodeParserService $shortcodeParser
    ): void {
        $campaign = DB::table('campaigns')->where('id', $this->campaignId)->first();
        if (! $campaign || in_array($campaign->status, ['paused', 'canceled', 'sent'], true)) {
            Log::info("Campaign {$this->campaignId} is not in a dispatchable state: " . ($campaign->status ?? 'missing'));

            return;
        }

        $newsletter = DB::table('newsletters')->where('id', $campaign->newsletter_id)->first();
        if (! $newsletter) {
            Log::error("Newsletter {$campaign->newsletter_id} not found for campaign {$campaign->id}");

            return;
        }

        // Load Account via Eloquent so encrypted settings are transparently decrypted
        $account = Account::find($newsletter->account_id);
        if ($account && in_array($account->status, ['paused', 'blocked'], true)) {
            DB::table('campaigns')->where('id', $campaign->id)->update([
                'status' => 'paused',
                'pause_reason' => 'Account is paused: ' . ($account->pause_reason ?? 'Administrative pause'),
            ]);
            Log::warning("Campaign {$campaign->id} paused because account {$account->id} is {$account->status}.");

            return;
        }

        $accountSettings = $account?->settings ?? [];
        $sendRateLimit = max(1, min(200, (int) ($accountSettings['send_rate_limit'] ?? 14)));
        $configurationSet = ! empty($accountSettings['ses_configuration_set'])
            ? (string) $accountSettings['ses_configuration_set']
            : (config('services.ses.configuration_set') ?: null);

        // Safety Pre-Check using RateLimiterAndProtectionService::evaluateSafety
        $safety = RateLimiterAndProtectionService::evaluateSafety(
            (int) $campaign->sent_count,
            (int) $campaign->bounce_count,
            (int) $campaign->complaint_count
        );

        if ($safety['action'] === 'pause' || $safety['action'] === 'block') {
            $reason = "Auto-paused: {$safety['metric']} rate (" . round($safety['rate'] * 100, 2) . '%) exceeded threshold (' . round($safety['limit'] * 100, 2) . '%).';
            DB::table('campaigns')->where('id', $campaign->id)->update([
                'status' => 'paused',
                'pause_reason' => $reason,
                'pause_code' => $safety['action'],
            ]);

            if ($safety['action'] === 'block' && $account) {
                $account->update([
                    'status' => 'paused',
                    'paused_by' => 'rates',
                    'paused_at' => now(),
                    'pause_reason' => "Automated circuit-breaker: {$safety['metric']} rate (" . round($safety['rate'] * 100, 2) . '%) breached safety threshold.',
                ]);
                Log::critical("Account {$account->id} auto-paused by deliverability circuit-breaker.");
            }

            try {
                app(AlertDispatcherService::class)->dispatch([
                    'title' => "Campaign Auto-Paused: {$safety['metric']} rate breached",
                    'message' => $reason,
                    'priority' => $safety['action'] === 'block' ? 'critical' : 'warning',
                    'action' => 'circuit_breaker.tripped',
                    'account_id' => $newsletter->account_id,
                    'tags' => ['circuit-breaker', (string) $safety['metric']],
                    'context' => [
                        'campaign_id' => $campaign->id,
                        'rate' => $safety['rate'],
                        'limit' => $safety['limit'],
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::error("[SendCampaignBatchJob] Alert dispatch failed: {$e->getMessage()}");
            }

            return;
        }

        if (in_array($campaign->status, ['draft', 'scheduled'], true)) {
            DB::table('campaigns')->where('id', $campaign->id)->update([
                'status' => 'sending',
                'sent_at' => now(),
            ]);
        }

        $alreadyMessagedContactIds = DB::table('messages')
            ->where('campaign_id', $campaign->id)
            ->pluck('contact_id');

        $sentCountSoFar = $alreadyMessagedContactIds->count();
        $audienceLimit = isset($campaign->audience_limit) && (int) $campaign->audience_limit > 0
            ? (int) $campaign->audience_limit
            : null;

        if ($audienceLimit !== null && $sentCountSoFar >= $audienceLimit) {
            DB::table('campaigns')->where('id', $campaign->id)->update(['status' => 'sent']);

            return;
        }

        $unmessagedQuery = DB::table('contacts')
            ->where('newsletter_id', $newsletter->id)
            ->where('status', 'subscribed')
            ->whereNotIn('id', $alreadyMessagedContactIds);

        // Prioritize most engaged readers first when spread/warm-up send is enabled
        if (! empty($campaign->is_spread_send)) {
            $unmessagedQuery->orderByDesc('open_count')->orderByDesc('click_count');
        }

        // Initialize First-Send / Warm-Up Safety Slicing
        if ($campaign->first_send_slice === null) {
            $totalEligible = (clone $unmessagedQuery)->count();
            $neverMailedCount = (clone $unmessagedQuery)->whereNull('last_sent_at')->count();

            if (! empty($campaign->is_spread_send) && $totalEligible >= 40) {
                $slice = max(5, (int) ceil($totalEligible * 0.05));
                if ($totalEligible > $slice) {
                    $held = $totalEligible - $slice;
                    $checkAt = now()->addMinutes(180);
                    DB::table('campaigns')->where('id', $campaign->id)->update([
                        'first_send_slice' => $slice,
                        'first_send_held' => $held,
                        'first_send_check_at' => $checkAt,
                    ]);
                    $campaign->first_send_slice = $slice;
                    $campaign->first_send_held = $held;
                    $campaign->first_send_check_at = $checkAt;
                }
            } elseif ($neverMailedCount > 50) {
                $slice = min(500, max(50, (int) round($neverMailedCount * 0.10)));
                if ($neverMailedCount > $slice) {
                    $held = $neverMailedCount - $slice;
                    $checkAt = now()->addMinutes(15);
                    DB::table('campaigns')->where('id', $campaign->id)->update([
                        'first_send_slice' => $slice,
                        'first_send_held' => $held,
                        'first_send_check_at' => $checkAt,
                    ]);
                    $campaign->first_send_slice = $slice;
                    $campaign->first_send_held = $held;
                    $campaign->first_send_check_at = $checkAt;
                    Log::info("Campaign {$campaign->id}: First-send slicing activated. Slice={$slice}, Held={$held}");
                }
            }
        }

        $effectiveBatchCap = $this->batchSize;
        if ($audienceLimit !== null) {
            $effectiveBatchCap = min($effectiveBatchCap, max(0, $audienceLimit - $sentCountSoFar));
        }

        $sliceActive = $campaign->first_send_slice !== null && $campaign->first_send_checked_at === null;

        if ($sliceActive) {
            if ($sentCountSoFar >= $campaign->first_send_slice) {
                if (now() < Carbon::parse($campaign->first_send_check_at)) {
                    Log::info("Campaign {$campaign->id}: First-send slice held until {$campaign->first_send_check_at}");

                    return;
                }

                $abWinner = $campaign->ab_winner;
                if ($campaign->ab_test_enabled && empty($abWinner)) {
                    $freshCamp = DB::table('campaigns')->where('id', $campaign->id)->first();
                    $abWinner = ($freshCamp->variant_b_opens > $freshCamp->variant_a_opens) ? 'b' : 'a';
                    DB::table('campaigns')->where('id', $campaign->id)->update([
                        'first_send_checked_at' => now(),
                        'ab_winner' => $abWinner,
                    ]);
                    $campaign->ab_winner = $abWinner;
                } else {
                    DB::table('campaigns')->where('id', $campaign->id)->update([
                        'first_send_checked_at' => now(),
                    ]);
                }
                $campaign->first_send_checked_at = now();
                $recipients = (clone $unmessagedQuery)->limit($effectiveBatchCap)->get();
            } else {
                $remainingInSlice = $campaign->first_send_slice - $sentCountSoFar;
                $batchLimit = min($effectiveBatchCap, $remainingInSlice);
                $recipients = (clone $unmessagedQuery)->limit($batchLimit)->get();
            }
        } else {
            $recipients = (clone $unmessagedQuery)->limit($effectiveBatchCap)->get();
        }

        if ($recipients->isEmpty()) {
            DB::table('campaigns')->where('id', $campaign->id)->update([
                'status' => 'sent',
            ]);

            return;
        }

        // Cross-check global/account suppression hashes so a contact suppressed on Newsletter A is never mailed on Newsletter B
        $appKey = (string) config('app.key');
        $hashMap = [];
        foreach ($recipients as $rec) {
            $hashMap[$rec->id] = hash_hmac('sha256', strtolower(trim($rec->email)), $appKey);
        }
        $suppressedHashes = DB::table('suppressions')
            ->whereIn('email_hash', array_values($hashMap))
            ->pluck('email_hash')
            ->flip()
            ->all();

        $appUrl = rtrim((string) config('app.url', 'http://spiggle-mail.local'), '/');
        $actualDispatchedInBatch = 0;

        foreach ($recipients as $idx => $recipient) {
            $recipientHash = $hashMap[$recipient->id];
            if (isset($suppressedHashes[$recipientHash])) {
                // Record skipped/suppressed contact so we never retry them in next batch
                DB::table('contacts')->where('id', $recipient->id)->update(['status' => 'unsubscribed']);
                DB::table('messages')->insert([
                    'id' => (string) Str::ulid(),
                    'campaign_id' => $campaign->id,
                    'contact_id' => $recipient->id,
                    'ses_message_id' => null,
                    'status' => 'failed',
                    'error' => 'Skipped: address present on global suppression list.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            $messageUlid = (string) Str::ulid();

            $abVariant = null;
            $chosenSubject = $campaign->subject;

            if ($campaign->ab_test_enabled && ! empty($campaign->subject_b)) {
                if (! empty($campaign->ab_winner)) {
                    $abVariant = $campaign->ab_winner;
                    $chosenSubject = ($campaign->ab_winner === 'b') ? $campaign->subject_b : $campaign->subject;
                } else {
                    $abVariant = (($sentCountSoFar + $idx) % 2 === 0) ? 'a' : 'b';
                    $chosenSubject = ($abVariant === 'b') ? $campaign->subject_b : $campaign->subject;
                }
            }

            $unsubscribeHeaders = UnsubscribeHeaderService::buildHeaders(
                $newsletter->slug,
                $recipient->id,
                $appUrl
            );

            $unsubscribeUrl = UnsubscribeHeaderService::landingUrl(
                $newsletter->slug,
                $recipient->id,
                $appUrl
            );

            $contactData = [
                'id' => $recipient->id,
                'email' => $recipient->email,
                'name' => explode('@', $recipient->email)[0],
            ];

            $newsletterData = [
                'name' => $newsletter->name,
                'from_email' => $newsletter->from_email,
                'postal_address' => $newsletter->postal_address,
            ];

            $personalizedHtml = $shortcodeParser->parse(
                $campaign->html ?? '',
                $contactData,
                $newsletterData,
                $unsubscribeUrl
            );

            $personalizedSubject = $shortcodeParser->parse(
                $chosenSubject,
                $contactData,
                $newsletterData,
                $unsubscribeUrl
            );

            // 1. Rewrite relative /storage/... image URLs to absolute URLs so external email clients render images
            $personalizedHtml = $this->absolutizeStorageUrls($personalizedHtml, $appUrl);

            // 2. Enforce CAN-SPAM / RFC compliance footer if author omitted {{ unsubscribe_url }}
            $personalizedHtml = $this->ensureComplianceFooter(
                $personalizedHtml,
                $unsubscribeUrl,
                (string) $newsletter->name,
                (string) $newsletter->postal_address
            );

            // Generate plain-text body BEFORE rewriting links to tracking tokens so plain-text URLs stay clean
            $plainTextBody = HtmlSanitizer::toPlainText($personalizedHtml);

            // 3. Rewrite external links for click tracking & inject 1x1 open pixel
            $trackedHtml = $this->injectTracking($personalizedHtml, $messageUlid, $appUrl);

            $response = $emailProvider->send([
                'to' => $recipient->email,
                'from' => "{$newsletter->from_name} <{$newsletter->from_email}>",
                'reply_to' => $newsletter->reply_to ?: $newsletter->from_email,
                'subject' => $personalizedSubject,
                'html' => $trackedHtml,
                'text' => $plainTextBody,
                'headers' => $unsubscribeHeaders,
                'configuration_set' => $configurationSet,
                'feedback_id' => (string) $campaign->id,
            ]);

            $sendFailed = is_array($response) && ($response['status'] ?? 'success') === 'failed';
            $sesMessageId = is_string($response) ? $response : ($response['message_id'] ?? null);
            $errorDetail = is_array($response) ? ($response['error'] ?? null) : null;

            DB::table('messages')->insert([
                'id' => $messageUlid,
                'campaign_id' => $campaign->id,
                'contact_id' => $recipient->id,
                'ses_message_id' => $sesMessageId,
                'status' => $sendFailed ? 'failed' : 'sent',
                'ab_variant' => $abVariant,
                'error' => $errorDetail,
                'sent_at' => $sendFailed ? null : now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! $sendFailed) {
                $actualDispatchedInBatch++;
                DB::table('contacts')->where('id', $recipient->id)->update([
                    'last_sent_at' => now(),
                    'ignored_issues_count' => DB::raw('ignored_issues_count + 1'),
                ]);

                DB::table('campaigns')->where('id', $campaign->id)->increment('sent_count');
            }
        }

        $totalProcessed = DB::table('messages')->where('campaign_id', $campaign->id)->count();
        if ($audienceLimit !== null && $totalProcessed >= $audienceLimit) {
            DB::table('campaigns')->where('id', $campaign->id)->update(['status' => 'sent']);

            return;
        }

        $remaining = DB::table('contacts')
            ->where('newsletter_id', $newsletter->id)
            ->where('status', 'subscribed')
            ->whereNotIn('id', DB::table('messages')->where('campaign_id', $campaign->id)->pluck('contact_id'))
            ->count();

        if ($remaining > 0) {
            if ($sliceActive && $totalProcessed >= $campaign->first_send_slice) {
                Log::info("Campaign {$campaign->id}: First-send slice completed. Holding remainder until {$campaign->first_send_check_at}");
                if (config('queue.default') !== 'sync' && $campaign->first_send_check_at) {
                    self::dispatch($campaign->id, $this->batchSize)
                        ->delay(Carbon::parse($campaign->first_send_check_at));
                }

                return;
            }

            // Pace next batch according to account send_rate_limit (emails/sec)
            $delaySeconds = max(1, (int) ceil(max(1, $actualDispatchedInBatch) / $sendRateLimit));
            self::dispatch($campaign->id, $this->batchSize)->delay(now()->addSeconds($delaySeconds));
        } else {
            DB::table('campaigns')->where('id', $campaign->id)->update([
                'status' => 'sent',
            ]);
        }
    }

    private function absolutizeStorageUrls(string $html, string $appUrl): string
    {
        return preg_replace(
            '/\bsrc=(["\'])\/storage\//i',
            'src=$1' . $appUrl . '/storage/',
            $html
        ) ?? $html;
    }

    private function ensureComplianceFooter(
        string $html,
        string $unsubscribeUrl,
        string $newsletterName,
        string $postalAddress
    ): string {
        if (str_contains($html, '/unsubscribe?token=') || str_contains($html, $unsubscribeUrl)) {
            return $html;
        }

        $safeName = htmlspecialchars($newsletterName, ENT_QUOTES, 'UTF-8');
        $safeAddress = htmlspecialchars($postalAddress, ENT_QUOTES, 'UTF-8');
        $safeUnsub = htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8');

        $footer = <<<HTML
<div style="margin-top:32px;padding-top:16px;border-top:1px solid #e4e4e7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:12px;color:#71717a;text-align:center;line-height:1.6;">
  <p style="margin:0 0 4px 0;">{$safeName} &bull; {$safeAddress}</p>
  <p style="margin:0;"><a href="{$safeUnsub}" style="color:#52525b;text-decoration:underline;">Unsubscribe</a> from future emails.</p>
</div>
HTML;

        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $footer . '</body>', $html, 1) ?? ($html . $footer);
        }

        return $html . $footer;
    }

    private function injectTracking(string $html, string $messageId, string $appUrl): string
    {
        $rewritten = preg_replace_callback(
            '/<a\b([^>]*?)\bhref=(["\'])(https?:\/\/[^"\']+)\2([^>]*)>/is',
            static function (array $m) use ($messageId, $appUrl): string {
                $before = $m[1];
                $quote = $m[2];
                $targetUrl = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $after = $m[4];

                // Do not wrap unsubscribe, confirmation, or already-wrapped tracking URLs
                if (
                    str_contains($targetUrl, '/unsubscribe')
                    || str_contains($targetUrl, '/confirm')
                    || str_contains($targetUrl, '/keep')
                    || str_contains($targetUrl, '/c/')
                ) {
                    return $m[0];
                }

                $token = TrackingTokenService::createClickToken($messageId, $targetUrl);
                $trackUrl = "{$appUrl}/c/{$token}";

                return "<a{$before}href={$quote}{$trackUrl}{$quote}{$after}>";
            },
            $html
        ) ?? $html;

        $openToken = TrackingTokenService::createOpenToken($messageId);
        $pixel = "<img src=\"{$appUrl}/o/{$openToken}\" width=\"1\" height=\"1\" alt=\"\" style=\"display:none;width:1px;height:1px;border:0;\" />";

        if (stripos($rewritten, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $pixel . '</body>', $rewritten, 1) ?? ($rewritten . $pixel);
        }

        return $rewritten . $pixel;
    }
}
