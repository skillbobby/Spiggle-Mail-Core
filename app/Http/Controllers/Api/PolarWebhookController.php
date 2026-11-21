<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Services\Billing\PolarBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PolarWebhookController extends Controller
{
    public function handle(Request $request, PolarBillingService $billingService): JsonResponse
    {
        $rawPayload = $request->getContent();
        $headers = $request->headers->all();

        if (! $billingService->verifyWebhookSignature($rawPayload, $headers)) {
            Log::warning('[PolarWebhookController] Invalid webhook signature detected.');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = json_decode($rawPayload, true);
        if (! is_array($event) || empty($event['type'])) {
            return response()->json(['error' => 'Invalid payload format'], 400);
        }

        $eventType = $event['type'];
        $data = $event['data'] ?? [];

        Log::info("[PolarWebhookController] Received event: {$eventType}", [
            'id' => $event['id'] ?? null,
            'event_type' => $eventType,
        ]);

        switch ($eventType) {
            case 'subscription.created':
            case 'subscription.active':
            case 'subscription.updated':
                $this->handleSubscriptionActive($data);
                break;

            case 'subscription.canceled':
                $this->handleSubscriptionCanceled($data);
                break;

            case 'subscription.revoked':
                $this->handleSubscriptionRevoked($data);
                break;

            case 'subscription.past_due':
                $this->handleSubscriptionPastDue($data);
                break;

            case 'order.refunded':
            case 'refund.created':
                $this->handleRefund($data);
                break;

            default:
                Log::debug("[PolarWebhookController] Unhandled event type: {$eventType}");
                break;
        }

        return response()->json(['status' => 'handled']);
    }

    private function findAccountFromPayload(array $data): ?Account
    {
        $accountId = $data['metadata']['account_id']
            ?? $data['customer']['external_id']
            ?? $data['customer_external_id']
            ?? null;

        if ($accountId) {
            $account = Account::find($accountId);
            if ($account) {
                return $account;
            }
        }

        $customerId = $data['customer_id'] ?? $data['customer']['id'] ?? null;
        if ($customerId) {
            $account = Account::where('settings->polar_customer_id', $customerId)->first();
            if ($account) {
                return $account;
            }
        }

        $subscriptionId = $data['subscription_id'] ?? $data['id'] ?? null;
        if ($subscriptionId) {
            $account = Account::where('settings->polar_subscription_id', $subscriptionId)->first();
            if ($account) {
                return $account;
            }
        }

        $customerEmail = $data['customer']['email'] ?? $data['customer_email'] ?? null;
        if ($customerEmail) {
            return Account::whereHas('user', function ($q) use ($customerEmail) {
                $q->where('email', $customerEmail);
            })->first();
        }

        return null;
    }

    private function handleSubscriptionActive(array $data): void
    {
        $account = $this->findAccountFromPayload($data);
        if (! $account) {
            Log::warning('[PolarWebhookController] Account not found for active subscription', $data);

            return;
        }

        $productMetadata = $data['product']['metadata'] ?? [];
        $tier = $productMetadata['tier'] ?? 'starter';
        $includedSends = (int) ($productMetadata['included_sends'] ?? 25000);
        $customerId = $data['customer_id'] ?? ($data['customer']['id'] ?? null);

        $settings = $account->settings ?? [];
        $settings['plan'] = $tier;
        $settings['monthly_sends_limit'] = $includedSends;
        $settings['polar_subscription_id'] = $data['id'] ?? null;
        if ($customerId) {
            $settings['polar_customer_id'] = $customerId;
        }
        $settings['subscription_status'] = 'active';
        $settings['cancel_at_period_end'] = false;
        $settings['current_period_end'] = $data['current_period_end'] ?? null;

        $account->settings = $settings;
        if ($account->status === 'paused' && $account->pause_reason === 'quota_reached') {
            $account->status = 'active';
            $account->pause_reason = null;
            $account->paused_at = null;
        }
        $account->save();

        ActivityLog::record(
            $account->id,
            'subscription.activated',
            "Plan upgraded/activated to {$tier} ({$includedSends} sends/month)."
        );
    }

    private function handleSubscriptionCanceled(array $data): void
    {
        $account = $this->findAccountFromPayload($data);
        if (! $account) {
            return;
        }

        $settings = $account->settings ?? [];
        $settings['cancel_at_period_end'] = true;
        $settings['subscription_status'] = 'canceled';
        $settings['cancel_effective_at'] = $data['ends_at'] ?? $data['current_period_end'] ?? null;

        $account->settings = $settings;
        $account->save();

        ActivityLog::record(
            $account->id,
            'subscription.canceled',
            "Subscription marked to cancel at end of billing cycle ({$settings['cancel_effective_at']})."
        );
    }

    private function handleSubscriptionRevoked(array $data): void
    {
        $account = $this->findAccountFromPayload($data);
        if (! $account) {
            return;
        }

        $settings = $account->settings ?? [];
        $settings['plan'] = 'free';
        $settings['subscription_status'] = 'revoked';
        $settings['monthly_sends_limit'] = 0;
        $account->settings = $settings;
        $account->save();

        // Safeguard: immediately pause active or queued campaigns
        $this->haltActiveCampaigns($account, 'paused_subscription_revoked');

        ActivityLog::record(
            $account->id,
            'subscription.revoked',
            'Subscription access ended. Cloud sends set to 0 and active broadcasts held.'
        );
    }

    private function handleSubscriptionPastDue(array $data): void
    {
        $account = $this->findAccountFromPayload($data);
        if (! $account) {
            return;
        }

        $settings = $account->settings ?? [];
        $settings['subscription_status'] = 'past_due';
        $account->settings = $settings;
        $account->save();

        // Pause sending to prevent runaway uncollectible meter charges
        $this->haltActiveCampaigns($account, 'paused_billing_past_due');

        ActivityLog::record(
            $account->id,
            'subscription.past_due',
            'Subscription payment failed. Outgoing broadcasts temporarily held until payment card is updated.'
        );
    }

    private function handleRefund(array $data): void
    {
        $account = $this->findAccountFromPayload($data);
        if (! $account) {
            return;
        }

        $settings = $account->settings ?? [];
        $settings['plan'] = 'free';
        $settings['subscription_status'] = 'refunded';
        $settings['monthly_sends_limit'] = 0;
        $account->settings = $settings;
        $account->status = 'paused';
        $account->pause_reason = 'refund_issued';
        $account->paused_at = now();
        $account->save();

        // Immediate Killswitch: halt any running campaigns right now
        $this->haltActiveCampaigns($account, 'paused_refund_revoked');

        ActivityLog::record(
            $account->id,
            'subscription.refunded',
            'Refund issued. Account sending immediately revoked and all active broadcasts halted.'
        );
    }

    private function haltActiveCampaigns(Account $account, string $haltStatus): void
    {
        $newsletterIds = $account->newsletters()->pluck('id');
        Campaign::whereIn('newsletter_id', $newsletterIds)
            ->whereIn('status', ['draft', 'scheduled', 'sending'])
            ->update([
                'status' => $haltStatus,
                'updated_at' => now(),
            ]);
    }
}
