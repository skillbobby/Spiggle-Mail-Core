<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Billing\PolarBillingService;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingApiController extends Controller
{
    public function createCheckout(Request $request, PolarBillingService $billingService): JsonResponse
    {
        $validated = $request->validate([
            'tier' => ['required', 'string', 'in:starter,growth,scale'],
            'interval' => ['nullable', 'string', 'in:month,year'],
        ]);

        $account = CurrentWorkspace::get($request);
        if (! $account) {
            return response()->json(['error' => 'No active workspace.'], 403);
        }

        $tier = $validated['tier'];
        $interval = $validated['interval'] ?? 'month';

        $result = $billingService->createCheckoutSession($account, $tier, $interval);

        if (! $result['ok']) {
            return response()->json(['error' => $result['error'] ?? 'Checkout failed'], 422);
        }

        return response()->json([
            'ok' => true,
            'checkout_url' => $result['url'],
            'checkout_id' => $result['checkout_id'],
        ]);
    }

    public function portalUrl(Request $request, PolarBillingService $billingService): JsonResponse
    {
        $account = CurrentWorkspace::get($request);
        if (! $account) {
            return response()->json(['error' => 'No active workspace.'], 403);
        }

        $portalUrl = $billingService->getCustomerPortalUrl($account);
        if (! $portalUrl) {
            return response()->json(['error' => 'No active billing portal session found.'], 404);
        }

        return response()->json([
            'ok' => true,
            'portal_url' => $portalUrl,
        ]);
    }

    public function getSubscription(Request $request): JsonResponse
    {
        $account = CurrentWorkspace::get($request);
        if (! $account) {
            return response()->json(['error' => 'No active workspace.'], 403);
        }

        $settings = $account->settings ?? [];
        $plan = $settings['plan'] ?? 'free';
        $quota = (int) ($settings['monthly_sends_limit'] ?? 0);
        $status = $settings['subscription_status'] ?? ($account->status === 'active' ? 'active' : 'inactive');
        $pauseAtLimit = (bool) ($settings['pause_at_quota_limit'] ?? false);

        // Count messages sent in current calendar month
        $sendsThisMonth = DB::table('messages')
            ->join('campaigns', 'messages.campaign_id', '=', 'campaigns.id')
            ->join('newsletters', 'campaigns.newsletter_id', '=', 'newsletters.id')
            ->where('newsletters.account_id', $account->id)
            ->where('messages.sent_at', '>=', now()->startOfMonth())
            ->count();

        return response()->json([
            'plan' => $plan,
            'status' => $status,
            'monthly_quota' => $quota,
            'sends_this_month' => $sendsThisMonth,
            'quota_remaining' => max(0, $quota - $sendsThisMonth),
            'pause_at_quota_limit' => $pauseAtLimit,
            'cancel_at_period_end' => (bool) ($settings['cancel_at_period_end'] ?? false),
            'current_period_end' => $settings['current_period_end'] ?? null,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pause_at_quota_limit' => ['required', 'boolean'],
        ]);

        $account = CurrentWorkspace::get($request);
        if (! $account) {
            return response()->json(['error' => 'No active workspace.'], 403);
        }

        $settings = $account->settings ?? [];
        $settings['pause_at_quota_limit'] = $validated['pause_at_quota_limit'];
        $account->settings = $settings;
        $account->save();

        return response()->json([
            'ok' => true,
            'pause_at_quota_limit' => $settings['pause_at_quota_limit'],
        ]);
    }
}
