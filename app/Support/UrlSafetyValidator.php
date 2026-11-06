<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use InvalidArgumentException;

final class UrlSafetyValidator
{
    private const SCANNER_USER_AGENT_PATTERNS = [
        'barracuda',
        'mimecast',
        'proofpoint',
        'safelinks',
        'microsoft office',
        'urlscan',
        'symantec',
        'Sophos',
        'trendmicro',
        'slackbot-linkexpanding',
        'discordbot',
        'whatsapp',
        'telegrambot',
    ];

    /**
     * Enforces HTTPS and blocks SSRF targets (localhost, loopback, RFC1918 private,
     * link-local AWS/GCP metadata 169.254.169.254, and reserved IP ranges).
     */
    public static function assertSafeHttpsUrl(string $url): void
    {
        $trimmed = trim($url);
        $parts = parse_url($trimmed);

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            throw new InvalidArgumentException('Webhook URL must use HTTPS and include a valid public hostname.');
        }

        $host = strtolower(trim((string) $parts['host'], '[]'));

        if (
            in_array($host, ['localhost', '0.0.0.0', '::1', '127.0.0.1', '169.254.169.254', 'metadata.google.internal'], true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.localhost')
        ) {
            throw new InvalidArgumentException('Webhook URL cannot target local, internal, or cloud metadata hosts.');
        }

        // If host is a literal IP, reject private or reserved ranges immediately
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException('Webhook URL cannot target private, loopback, or reserved IP addresses.');
            }

            return;
        }

        // In live/non-testing environments, resolve DNS to prevent DNS-rebinding SSRF
        if (! app()->environment('testing')) {
            $ips = @gethostbynamel($host) ?: [];
            foreach ($ips as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new InvalidArgumentException('Webhook hostname resolves to a disallowed private or reserved IP address.');
                }
            }
        }
    }

    public static function isSafeHttpsUrl(string $url): bool
    {
        try {
            self::assertSafeHttpsUrl($url);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Validates that an AWS SNS SigningCertURL or SubscribeURL originates strictly
     * from an official regional Amazon SNS HTTPS endpoint.
     */
    public static function isValidAwsSnsUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts)) {
            return false;
        }

        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return (bool) preg_match('/^sns\.[a-z0-9\-]+\.amazonaws\.com(\.cn)?$/i', $host);
    }

    /**
     * Detects automated enterprise email security scanners / link pre-fetchers or HEAD probes.
     */
    public static function isBotScannerRequest(Request $request): bool
    {
        if ($request->isMethod('HEAD')) {
            return true;
        }

        $ua = strtolower((string) $request->userAgent());
        if ($ua === '') {
            return false;
        }

        foreach (self::SCANNER_USER_AGENT_PATTERNS as $pattern) {
            if (str_contains($ua, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }
}
