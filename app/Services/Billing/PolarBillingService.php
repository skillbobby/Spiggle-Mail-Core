<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Account;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PolarBillingService
{
    private string $baseUrl = 'https://api.polar.sh/v1';

    public function isConfigured(): bool
    {
        return ! empty(config('services.polar.token'));
    }

    public function getProductId(string $tier, string $interval = 'month'): ?string
    {
        $tier = strtolower(trim($tier));
        $interval = strtolower(trim($interval)) === 'year' ? 'year' : 'month';
        $key = "{$tier}_{$interval}";

        return config("services.polar.products.{$key}");
    }

    /**
     * Create an authenticated Polar Checkout Session for an account.
     *
     * @return array{ok: bool, url?: string, checkout_id?: string, error?: string}
     */
    public function createCheckoutSession(
        Account $account,
        string $tier,
        string $interval = 'month',
        ?string $successUrl = null,
        ?string $returnUrl = null
    ): array {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'error' => 'Polar billing is not configured in this environment.',
            ];
        }

        $productId = $this->getProductId($tier, $interval);
        if (! $productId) {
            return [
                'ok' => false,
                'error' => "Unknown plan tier [{$tier}] and interval [{$interval}].",
            ];
        }

        $successUrl = $successUrl ?? url('/billing/success?checkout_id={CHECKOUT_ID}');
        $returnUrl = $returnUrl ?? url('/billing');

        $user = $account->user;
        $customerEmail = $user?->email;
        $customerName = $account->name ?? ($user?->name ?? 'MailJinny User');

        $payload = [
            'products' => [$productId],
            'customer_email' => $customerEmail,
            'customer_name' => $customerName,
            'customer_external_id' => (string) $account->id,
            'success_url' => $successUrl,
            'return_url' => $returnUrl,
            'metadata' => [
                'account_id' => (string) $account->id,
                'tier' => $tier,
                'interval' => $interval,
            ],
        ];

        try {
            $response = Http::withToken(config('services.polar.token'))
                ->acceptJson()
                ->asJson()
                ->post("{$this->baseUrl}/checkouts/", $payload);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'ok' => true,
                    'url' => $data['url'] ?? null,
                    'checkout_id' => $data['id'] ?? null,
                ];
            }

            Log::error('[PolarBillingService] Failed to create checkout session', [
                'status' => $response->status(),
                'body' => $response->body(),
                'account_id' => $account->id,
            ]);

            return [
                'ok' => false,
                'error' => $response->json('detail') ?? $response->json('message') ?? 'Polar checkout error',
            ];
        } catch (Throwable $e) {
            Log::error('[PolarBillingService] Exception creating checkout session: '.$e->getMessage(), [
                'account_id' => $account->id,
            ]);

            return [
                'ok' => false,
                'error' => 'Failed to connect to billing provider.',
            ];
        }
    }

    /**
     * Create a Customer Portal session URL where the user can manage cards, update tier, or cancel.
     */
    public function getCustomerPortalUrl(Account $account): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $customerId = $account->settings['polar_customer_id'] ?? null;
        if (! $customerId) {
            return null;
        }

        try {
            $response = Http::withToken(config('services.polar.token'))
                ->acceptJson()
                ->asJson()
                ->post("{$this->baseUrl}/customer-sessions/", [
                    'customer_id' => $customerId,
                ]);

            if ($response->successful()) {
                return $response->json('customer_portal_url');
            }
        } catch (Throwable $e) {
            Log::warning('[PolarBillingService] Failed to fetch customer portal: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Record dispatched email units on Polar's metered billing engine.
     */
    public function recordMeterUsage(Account $account, int $unitsCount): bool
    {
        if (! $this->isConfigured() || $unitsCount <= 0) {
            return false;
        }

        $customerId = $account->settings['polar_customer_id'] ?? null;
        $externalId = (string) $account->id;

        // Polar meter "Email Dispatches" uses a custom_multiplier of 1000 (1 unit = 1,000 sends)
        $scaledUnits = round($unitsCount / 1000.0, 4);

        $eventPayload = [
            'name' => 'email_dispatched',
            'timestamp' => now()->toIso8601String(),
            'metadata' => [
                'units' => $scaledUnits,
                'raw_emails' => $unitsCount,
                'account_id' => $externalId,
            ],
        ];

        if (! empty($customerId)) {
            $eventPayload['customer_id'] = $customerId;
        } else {
            $eventPayload['external_customer_id'] = $externalId;
        }

        try {
            $response = Http::withToken(config('services.polar.token'))
                ->acceptJson()
                ->asJson()
                ->post("{$this->baseUrl}/events/ingest", [
                    'events' => [$eventPayload],
                ]);

            if (! $response->successful()) {
                Log::warning('[PolarBillingService] Failed to ingest meter event: '.$response->body(), [
                    'status' => $response->status(),
                    'account_id' => $account->id,
                ]);
            }

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning('[PolarBillingService] Failed to ingest meter event: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Verify standard HMAC-SHA256 signature from Polar webhook.
     */
    public function verifyWebhookSignature(string $rawPayload, array $headers): bool
    {
        $secret = config('services.polar.webhook_secret');
        if (empty($secret)) {
            return false;
        }

        // Polar uses standard Webhook headers:
        // webhook-id, webhook-timestamp, webhook-signature
        $webhookId = $headers['webhook-id'][0] ?? $headers['webhook-id'] ?? null;
        $webhookTimestamp = $headers['webhook-timestamp'][0] ?? $headers['webhook-timestamp'] ?? null;
        $webhookSignature = $headers['webhook-signature'][0] ?? $headers['webhook-signature'] ?? null;

        if (! $webhookId || ! $webhookTimestamp || ! $webhookSignature) {
            return false;
        }

        // Verify timestamp freshness (within 5 minutes)
        if (abs(time() - (int) $webhookTimestamp) > 300) {
            Log::warning('[PolarBillingService] Webhook timestamp out of tolerance.');

            return false;
        }

        // Strip prefix "whsec_" if present
        $cleanSecret = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $secretKey = base64_decode($cleanSecret);

        $toSign = "{$webhookId}.{$webhookTimestamp}.{$rawPayload}";
        $expectedSignature = base64_encode(hash_hmac('sha256', $toSign, $secretKey, true));

        // Signatures are usually given as 'v1,sig' or space-separated
        $signatures = explode(' ', (string) $webhookSignature);
        foreach ($signatures as $sig) {
            $parts = explode(',', $sig, 2);
            $sigHash = count($parts) === 2 ? $parts[1] : $parts[0];
            if (hash_equals($expectedSignature, $sigHash)) {
                return true;
            }
        }

        return false;
    }
}
