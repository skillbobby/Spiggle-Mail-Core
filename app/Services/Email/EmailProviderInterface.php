<?php

declare(strict_types=1);

namespace App\Services\Email;

interface EmailProviderInterface
{
    /**
     * Send an email payload and return provider message ID.
     *
     * @param array{
     *     from: string,
     *     to: string,
     *     subject: string,
     *     html: string,
     *     text?: string,
     *     reply_to?: string,
     *     headers?: array<string, string>,
     *     configuration_set?: string,
     *     feedback_id?: string
     * } $payload
     * @return array{status: 'success'|'failed', message_id?: string, error?: string}
     */
    public function send(array $payload): array;

    /**
     * Send raw parameters directly (convenience wrapper).
     *
     * @param  array<string, string>  $headers
     * @return array{status: 'success'|'failed', message_id?: string, error?: string}
     */
    public function sendRaw(string $to, string $from, string $subject, string $html, array $headers = []): array;

    /**
     * Create or check domain identity verification tokens (DKIM, MailFrom).
     *
     * @return array{domain: string, status: 'pending'|'verified'|'failed', dkim_tokens: list<string>, mail_from: string}
     */
    public function getOrCreateDomainIdentity(string $domain): array;

    /**
     * Query sending account status, sandbox vs production mode, 24h quota, and max send rate.
     *
     * @return array{
     *     ok: bool,
     *     driver: string,
     *     region: string,
     *     production_access: bool,
     *     sending_enabled: bool,
     *     max_24_hour_send: float,
     *     max_send_rate: float,
     *     sent_last_24_hours: float,
     *     error?: string
     * }
     */
    public function getAccountQuota(): array;
}
