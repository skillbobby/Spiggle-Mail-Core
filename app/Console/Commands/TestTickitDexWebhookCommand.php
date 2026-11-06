<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Alerting\TickitDexWebhookService;
use Illuminate\Console\Command;

class TestTickitDexWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alert:test-tickitdex
                            {--title=SpiggleMail Health Test Alert : Ticket headline}
                            {--priority=warning : Priority: critical, high, warning, info}
                            {--message= : Optional detailed message body}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a test ticket to the configured TickitDex Support-Chat Webhook';

    /**
     * Execute the console command.
     */
    public function handle(TickitDexWebhookService $service): int
    {
        $this->info('Testing TickitDex webhook integration...');

        if (!$service->isEnabled()) {
            $this->warn('TickitDex integration is currently DISABLED. (Set TICKITDEX_ENABLED=true in .env)');
            return 1;
        }

        if (!$service->isConfigured()) {
            $this->error('TickitDex token is missing. (Set TICKITDEX_TOKEN in .env)');
            return 1;
        }

        $title = (string) $this->option('title');
        $priority = (string) $this->option('priority');
        $message = (string) ($this->option('message') ?: 'This is an automated verification ticket dispatched by SpiggleMail.');

        $this->line("Target Endpoint: {$service->getEndpointUrl()}");
        $this->line("Priority: {$priority}");

        $result = $service->createTicket([
            'title' => $title,
            'message' => $message,
            'priority' => $priority,
            'tags' => ['spiggle-mail', 'cli-test'],
            'context' => [
                'dispatched_via' => 'php artisan alert:test-tickitdex',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        if ($result['success']) {
            $this->info("Ticket dispatched successfully! Status: {$result['status_code']}");
            if (!empty($result['ticket_id'])) {
                $this->line("Ticket ID: {$result['ticket_id']}");
            }
            return 0;
        }

        $this->error("Dispatch failed: " . ($result['error'] ?? 'Unknown error'));
        return 1;
    }
}
