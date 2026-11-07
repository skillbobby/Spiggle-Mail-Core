<?php

declare(strict_types=1);

namespace App\Services\Email;

use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class MockEmailProvider implements EmailProviderInterface
{
    private string $mailpitHost;

    private int $mailpitPort;

    public function __construct(string $mailpitHost = '127.0.0.1', int $mailpitPort = 1025)
    {
        $this->mailpitHost = $mailpitHost;
        $this->mailpitPort = $mailpitPort;
    }

    public function send(array $payload): array
    {
        $headers = $payload['headers'] ?? [];
        if (! empty($payload['reply_to']) && ! isset($headers['Reply-To'])) {
            $headers['Reply-To'] = (string) $payload['reply_to'];
        }

        return $this->sendRaw(
            $payload['to'],
            $payload['from'],
            $payload['subject'],
            $payload['html'],
            $headers,
            $payload['text'] ?? HtmlSanitizer::toPlainText((string) $payload['html'])
        );
    }

    public function sendRaw(
        string $to,
        string $from,
        string $subject,
        string $html,
        array $headers = [],
        ?string $plainText = null
    ): array {
        $messageId = 'mock-ses-'.Str::uuid()->toString().'@spiggle-mail.local';
        $textBody = $plainText ?? HtmlSanitizer::toPlainText($html);

        // Try delivering to local Mailpit SMTP if available (skip socket wait in PHPUnit unless configured)
        $smtpSuccess = app()->environment('testing')
            ? false
            : $this->sendToMailpit($to, $from, $subject, $html, $textBody, $headers, $messageId);

        Log::info('Mock SES email dispatched', [
            'to' => $to,
            'from' => $from,
            'subject' => $subject,
            'message_id' => $messageId,
            'delivered_to_mailpit' => $smtpSuccess,
        ]);

        return [
            'status' => 'success',
            'message_id' => $messageId,
        ];
    }

    private function sendToMailpit(
        string $to,
        string $from,
        string $subject,
        string $html,
        string $text,
        array $headers,
        string $messageId
    ): bool {
        try {
            $socket = @fsockopen($this->mailpitHost, $this->mailpitPort, $errno, $errstr, 0.5);
            if (! $socket) {
                return false;
            }

            fgets($socket, 512);
            fwrite($socket, "HELO spiggle-mail.local\r\n");
            fgets($socket, 512);

            preg_match('/<([^>]+)>/', $from, $matches);
            $cleanFrom = $matches[1] ?? $from;

            fwrite($socket, "MAIL FROM:<{$cleanFrom}>\r\n");
            fgets($socket, 512);

            fwrite($socket, "RCPT TO:<{$to}>\r\n");
            fgets($socket, 512);

            fwrite($socket, "DATA\r\n");
            fgets($socket, 512);

            $boundary = '=_spiggle_'.Str::random(20);
            $msg = "From: {$from}\r\n";
            $msg .= "To: <{$to}>\r\n";
            $msg .= "Subject: {$subject}\r\n";
            $msg .= "Message-ID: <{$messageId}>\r\n";
            $msg .= "MIME-Version: 1.0\r\n";
            $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

            foreach ($headers as $key => $val) {
                $msg .= "{$key}: {$val}\r\n";
            }

            $msg .= "\r\n--{$boundary}\r\n";
            $msg .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $msg .= $text."\r\n";
            $msg .= "--{$boundary}\r\n";
            $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $msg .= $html."\r\n";
            $msg .= "--{$boundary}--\r\n.\r\n";

            fwrite($socket, $msg);
            fgets($socket, 512);

            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Mailpit socket transmission skipped: '.$e->getMessage());

            return false;
        }
    }

    public function getOrCreateDomainIdentity(string $domain): array
    {
        $clean = strtolower(trim($domain));

        return [
            'domain' => $clean,
            'status' => app()->environment('local', 'testing') ? 'verified' : 'pending',
            'dkim_tokens' => [
                'mocktoken1'.substr(md5($clean), 0, 8),
                'mocktoken2'.substr(md5($clean), 8, 8),
                'mocktoken3'.substr(md5($clean), 16, 8),
            ],
            'mail_from' => 'mail.'.$clean,
        ];
    }

    public function getAccountQuota(): array
    {
        return [
            'ok' => true,
            'driver' => 'mock',
            'region' => (string) config('services.ses.region', 'us-east-1'),
            'production_access' => true,
            'sending_enabled' => true,
            'max_24_hour_send' => 50000.0,
            'max_send_rate' => 14.0,
            'sent_last_24_hours' => 0.0,
        ];
    }
}
