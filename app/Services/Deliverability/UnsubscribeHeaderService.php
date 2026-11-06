<?php

declare(strict_types=1);

namespace App\Services\Deliverability;

use RuntimeException;

final class UnsubscribeHeaderService
{
    private static function signingKey(): string
    {
        $key = (string) config('app.key', '');
        if ($key === '') {
            if (app()->environment('production')) {
                throw new RuntimeException('APP_KEY must be configured to sign unsubscribe tokens.');
            }

            return 'testing-fallback-unsubscribe-key';
        }

        return $key;
    }

    /**
     * Generate 256-bit HMAC-signed token for unsubscribing.
     */
    public static function createUnsubscribeToken(string $contactId, string $newsletterSlug): string
    {
        $payload = json_encode(['c' => $contactId, 's' => $newsletterSlug]) ?: '{}';
        $sig = hash_hmac('sha256', $payload, self::signingKey());

        return rtrim(strtr(base64_encode($payload . '|' . $sig), '+/', '-_'), '=');
    }

    /**
     * Decode an unsubscribe token.
     *
     * @return array{contact_id: string, slug: string}|null
     */
    public static function decodeUnsubscribeToken(string $token): ?array
    {
        $data = base64_decode(strtr($token, '-_', '+/'), true);
        if (! $data || ! str_contains($data, '|')) {
            return null;
        }

        [$payload, $expectedSig] = explode('|', $data, 2);
        $fullSig = hash_hmac('sha256', $payload, self::signingKey());

        $valid = hash_equals($fullSig, $expectedSig)
            || (strlen($expectedSig) === 16 && hash_equals(substr($fullSig, 0, 16), $expectedSig));

        if (! $valid) {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded) || ! isset($decoded['c'], $decoded['s'])) {
            return null;
        }

        return [
            'contact_id' => (string) $decoded['c'],
            'slug' => (string) $decoded['s'],
        ];
    }

    /**
     * Generate RFC 8058 compliant One-Click Unsubscribe headers.
     *
     * @return array<string, string>
     */
    public static function buildHeaders(string $newsletterSlug, string $contactId, ?string $baseUrl = null): array
    {
        $appUrl = rtrim($baseUrl ?? (string) config('app.url', 'http://spiggle-mail.local'), '/');
        $token = self::createUnsubscribeToken($contactId, $newsletterSlug);

        $postUrl = "{$appUrl}/api/v1/unsubscribe/{$token}";
        $webUrl = "{$appUrl}/unsubscribe?token={$token}";

        return [
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            'List-Unsubscribe' => "<{$postUrl}>, <{$webUrl}>",
        ];
    }

    /**
     * Generate recipient-specific landing unsubscribe URL for in-email template links.
     */
    public static function landingUrl(string $newsletterSlug, string $contactId, ?string $baseUrl = null): string
    {
        $appUrl = rtrim($baseUrl ?? (string) config('app.url', 'http://spiggle-mail.local'), '/');
        $token = self::createUnsubscribeToken($contactId, $newsletterSlug);

        return "{$appUrl}/unsubscribe?token={$token}";
    }
}
