<?php

declare(strict_types=1);

namespace App\Services\Alerting;

use App\Models\Account;
use App\Support\UrlSafetyValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TickitDexWebhookService
{
    public const DEFAULT_ENDPOINT = 'https://tickitdex.com/support-chat/webhooks/tickets';

    /**
     * Get settings merging account settings with config defaults.
     *
     * @return array{
     *     enabled: bool,
     *     token: string|null,
     *     webhook_url: string,
     *     default_category: string,
     *     customer_email: string|null,
     *     min_priority: string,
     *     timeout: int
     * }
     */
    public function getSettings(?Account $account = null): array
    {
        $account = $account ?? (app()->runningInConsole() ? Account::first() : null);
        $accountTickit = $account?->settings['tickitdex'] ?? [];

        return [
            'enabled' => (bool) ($accountTickit['enabled'] ?? config('services.tickitdex.enabled', false)),
            'token' => ! empty($accountTickit['token']) ? (string) $accountTickit['token'] : config('services.tickitdex.token'),
            'webhook_url' => ! empty($accountTickit['webhook_url']) ? (string) $accountTickit['webhook_url'] : (config('services.tickitdex.webhook_url') ?: self::DEFAULT_ENDPOINT),
            'default_category' => (string) ($accountTickit['default_category'] ?? config('services.tickitdex.default_category', 'deliverability')),
            'customer_email' => $accountTickit['customer_email'] ?? config('services.tickitdex.customer_email'),
            'min_priority' => (string) ($accountTickit['min_priority'] ?? 'warning'),
            'timeout' => (int) ($accountTickit['timeout'] ?? config('services.tickitdex.timeout', 5)),
        ];
    }

    /**
     * Determine if TickitDex webhook integration is enabled.
     */
    public function isEnabled(?Account $account = null): bool
    {
        return $this->getSettings($account)['enabled'];
    }

    /**
     * Determine if TickitDex webhook integration is properly configured.
     */
    public function isConfigured(?Account $account = null): bool
    {
        $settings = $this->getSettings($account);

        return $settings['enabled'] && ! empty($settings['token']);
    }

    /**
     * Get the TickitDex API / Bearer token.
     */
    public function getToken(?Account $account = null): ?string
    {
        return $this->getSettings($account)['token'];
    }

    /**
     * Get the target webhook endpoint URL.
     */
    public function getEndpointUrl(?Account $account = null): string
    {
        return $this->getSettings($account)['webhook_url'];
    }

    /**
     * Normalize severity / priority to TickitDex supported priorities:
     * critical, high, warning (medium), info (low)
     */
    public function normalizePriority(?string $priority): string
    {
        return match (strtolower((string) $priority)) {
            'critical', 'danger', 'emergency', 'alert' => 'critical',
            'high', 'error' => 'high',
            'warning', 'warn', 'medium' => 'warning',
            default => 'info',
        };
    }

    /**
     * Dispatch a ticket creation request to TickitDex via webhook.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createTicket(array $data, ?Account $account = null): array
    {
        $settings = $this->getSettings($account);

        $token = (string) ($data['override_token'] ?? $settings['token'] ?? '');
        $url = (string) ($data['override_url'] ?? $settings['webhook_url']);

        // Allow test pings if an override token is passed even if disabled
        $isTestPing = ! empty($data['override_token']);

        if (! $isTestPing && ! $settings['enabled']) {
            return [
                'configured' => false,
                'enabled' => false,
                'success' => false,
                'error' => 'TickitDex webhook integration is disabled in Settings.',
            ];
        }

        if (empty($token)) {
            return [
                'configured' => false,
                'enabled' => true,
                'success' => false,
                'error' => 'TickitDex token is missing. Please configure your Bearer token in Settings.',
            ];
        }

        $title = (string) ($data['title'] ?? 'SpiggleMail Deliverability Alert');
        $message = (string) ($data['message'] ?? $title);

        $payload = [
            'title' => $title,
            'message' => $message,
            'sending_app' => 'spiggle-mail',
            'ticket_category' => (string) ($data['ticket_category'] ?? $settings['default_category']),
            'priority' => $this->normalizePriority($data['priority'] ?? 'warning'),
            'environment' => (string) ($data['environment'] ?? app()->environment()),
        ];

        // Customer / desk recipient email
        $customerEmail = $data['customer_email'] ?? $settings['customer_email'];
        if (! empty($customerEmail)) {
            $payload['customer_email'] = (string) $customerEmail;
        }

        // Idempotency key
        if (! empty($data['client_id'])) {
            $payload['client_id'] = (string) $data['client_id'];
        }

        // Tags
        $defaultTags = ['spiggle-mail', 'deliverability', 'circuit-breaker'];
        $tags = array_values(array_unique(array_filter(array_merge(
            $defaultTags,
            (array) ($data['tags'] ?? [])
        ))));
        $payload['tags'] = $tags;

        // Extra Context
        if (! empty($data['context']) && is_array($data['context'])) {
            $payload['context'] = $data['context'];
        }

        $timeout = (int) ($settings['timeout'] ?? 5);

        try {
            UrlSafetyValidator::assertSafeHttpsUrl($url);

            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($url, $payload);

            $success = $response->successful();
            $responseData = $response->json() ?? [];

            if ($success) {
                Log::info("[TickitDex Webhook] Ticket dispatched for '{$title}'", [
                    'status_code' => $response->status(),
                    'sending_app' => $payload['sending_app'],
                    'priority' => $payload['priority'],
                ]);
            } else {
                Log::warning("[TickitDex Webhook] Request failed for '{$title}': HTTP {$response->status()}", [
                    'body' => $response->body(),
                ]);
            }

            return [
                'configured' => true,
                'enabled' => true,
                'success' => $success,
                'status_code' => $response->status(),
                'ticket_id' => $responseData['ticket_id'] ?? $responseData['id'] ?? null,
                'response' => $responseData,
                'payload' => $payload,
                'error' => $success ? null : ($responseData['message'] ?? $responseData['error'] ?? "HTTP {$response->status()}"),
            ];
        } catch (\Throwable $e) {
            Log::error("[TickitDex Webhook] Connection error: {$e->getMessage()}", [
                'endpoint' => $url,
                'exception' => $e,
            ]);

            return [
                'configured' => true,
                'enabled' => true,
                'success' => false,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ];
        }
    }
}
