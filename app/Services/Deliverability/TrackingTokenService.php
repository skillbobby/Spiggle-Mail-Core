<?php

declare(strict_types=1);

namespace App\Services\Deliverability;

use RuntimeException;

final class TrackingTokenService
{
    private static function signingKey(): string
    {
        $key = (string) config('app.key', '');
        if ($key === '') {
            if (app()->environment('production')) {
                throw new RuntimeException('APP_KEY must be configured to sign tracking tokens.');
            }

            return 'testing-fallback-hmac-key';
        }

        return $key;
    }

    private static function verifySignature(string $payload, string $providedSig): bool
    {
        $fullSig = hash_hmac('sha256', $payload, self::signingKey());

        if (hash_equals($fullSig, $providedSig)) {
            return true;
        }

        // Backward compatibility for pre-existing 64-bit truncated tokens already in inboxes
        if (strlen($providedSig) === 16 && hash_equals(substr($fullSig, 0, 16), $providedSig)) {
            return true;
        }

        return false;
    }

    /**
     * Generate a 256-bit HMAC-signed token for open tracking.
     */
    public static function createOpenToken(string $messageId): string
    {
        $payload = json_encode(['m' => $messageId, 't' => time()]) ?: '{}';
        $sig = hash_hmac('sha256', $payload, self::signingKey());

        return rtrim(strtr(base64_encode($payload.'|'.$sig), '+/', '-_'), '=');
    }

    /**
     * Decode an open token and verify signature.
     */
    public static function decodeOpenToken(string $token): ?string
    {
        $data = base64_decode(strtr($token, '-_', '+/'), true);
        if (! $data || ! str_contains($data, '|')) {
            return null;
        }

        [$payload, $expectedSig] = explode('|', $data, 2);
        if (! self::verifySignature($payload, $expectedSig)) {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? ($decoded['m'] ?? null) : null;
    }

    /**
     * Generate a 256-bit HMAC-signed token for click tracking redirect.
     */
    public static function createClickToken(string $messageId, string $targetUrl): string
    {
        $payload = json_encode(['m' => $messageId, 'u' => $targetUrl, 't' => time()]) ?: '{}';
        $sig = hash_hmac('sha256', $payload, self::signingKey());

        return rtrim(strtr(base64_encode($payload.'|'.$sig), '+/', '-_'), '=');
    }

    /**
     * Decode a click token, returning [message_id, url] or null.
     *
     * @return array{message_id: string, url: string}|null
     */
    public static function decodeClickToken(string $token): ?array
    {
        $data = base64_decode(strtr($token, '-_', '+/'), true);
        if (! $data || ! str_contains($data, '|')) {
            return null;
        }

        [$payload, $expectedSig] = explode('|', $data, 2);
        if (! self::verifySignature($payload, $expectedSig)) {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded) || ! isset($decoded['m'], $decoded['u'])) {
            return null;
        }

        $url = trim((string) $decoded['u']);
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        return [
            'message_id' => (string) $decoded['m'],
            'url' => $url,
        ];
    }

    /**
     * Generate a short-lived signed export token for CSV downloads.
     */
    public static function createExportToken(string $accountId, int $ttlSeconds = 900): string
    {
        $payload = json_encode([
            'account_id' => $accountId,
            'exp' => time() + $ttlSeconds,
        ]) ?: '{}';
        $sig = hash_hmac('sha256', $payload, self::signingKey());

        return rtrim(strtr(base64_encode($payload.'|'.$sig), '+/', '-_'), '=');
    }

    /**
     * Decode a generic signed token (used by CSV export links).
     *
     * @return array<string, mixed>|null
     */
    public static function decodeToken(string $token): ?array
    {
        $data = base64_decode(strtr($token, '-_', '+/'), true);
        if (! $data || ! str_contains($data, '|')) {
            return null;
        }

        [$payload, $expectedSig] = explode('|', $data, 2);
        if (! self::verifySignature($payload, $expectedSig)) {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return null;
        }

        if (isset($decoded['exp']) && (int) $decoded['exp'] < time()) {
            return null;
        }

        return $decoded;
    }
}
