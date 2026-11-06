<?php

declare(strict_types=1);

namespace App\Services\Alerting;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertDispatcherService
{
    public function __construct(
        protected TickitDexWebhookService $tickitdex
    ) {}

    /**
     * Dispatch an operator alert across all configured channels.
     *
     * @param array{
     *     title: string,
     *     message: string,
     *     priority?: string,
     *     action?: string,
     *     account_id?: string|null,
     *     tags?: array<string>,
     *     context?: array<string, mixed>
     * } $alert
     * @return array<string, mixed>
     */
    public function dispatch(array $alert): array
    {
        $title = $alert['title'];
        $message = $alert['message'];
        $priority = $alert['priority'] ?? 'warning';
        $accountId = $alert['account_id'] ?? null;
        $context = $alert['context'] ?? [];

        Log::warning("[Operator Alert] {$title}: {$message}", [
            'priority' => $priority,
            'account_id' => $accountId,
            'context' => $context,
        ]);

        $results = [
            'logged' => true,
            'tickitdex' => null,
            'telegram' => null,
            'activity_log' => null,
        ];

        // 1. Record Activity Log
        try {
            $act = ActivityLog::record(
                action: $alert['action'] ?? 'alert.dispatched',
                description: "[{$priority}] {$title} - {$message}",
                accountId: $accountId,
                properties: array_merge(['priority' => $priority], $context)
            );
            $results['activity_log'] = $act->id;
        } catch (\Throwable $e) {
            Log::error("[Operator Alert] ActivityLog record failed: " . $e->getMessage());
        }

        // 2. Dispatch to TickitDex Support-Chat Webhook if configured
        if ($this->tickitdex->isConfigured()) {
            $results['tickitdex'] = $this->tickitdex->createTicket([
                'title' => $title,
                'message' => $message,
                'priority' => $priority,
                'tags' => array_merge(['circuit-breaker', 'operator-alert'], $alert['tags'] ?? []),
                'context' => array_merge($context, ['account_id' => $accountId]),
            ]);
        }

        // 3. Dispatch to Telegram if configured
        $telegramToken = config('services.telegram.bot_token');
        $telegramChatId = config('services.telegram.chat_id');
        if (config('services.telegram.enabled') && !empty($telegramToken) && !empty($telegramChatId)) {
            try {
                $text = "🚨 <b>[SpiggleMail Alert]</b> " . htmlspecialchars($title) . "\n\n"
                      . htmlspecialchars($message) . "\n"
                      . "<b>Priority:</b> " . strtoupper($priority);
                
                $res = Http::timeout(5)->post("https://api.telegram.org/bot{$telegramToken}/sendMessage", [
                    'chat_id' => $telegramChatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ]);
                $results['telegram'] = [
                    'success' => $res->successful(),
                    'status' => $res->status(),
                ];
            } catch (\Throwable $e) {
                $results['telegram'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
