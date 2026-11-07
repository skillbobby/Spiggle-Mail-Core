<?php

declare(strict_types=1);

namespace App\Support;

final class SecretBox
{
    public const MASK_PREFIX = '••••••••••••';

    public static function mask(?string $secret): string
    {
        if ($secret === null || $secret === '') {
            return '';
        }

        if (strlen($secret) <= 4) {
            return self::MASK_PREFIX;
        }

        return self::MASK_PREFIX.substr($secret, -4);
    }

    public static function isMasked(?string $input): bool
    {
        if ($input === null || $input === '') {
            return false;
        }

        return str_contains($input, '••••');
    }

    /**
     * Preserves the existing stored secret when the UI submits a masked placeholder.
     */
    public static function resolveSubmitted(?string $submitted, ?string $existing): string
    {
        if ($submitted === null) {
            return (string) ($existing ?? '');
        }

        $trimmed = trim($submitted);
        if ($trimmed === '') {
            return '';
        }

        if (self::isMasked($trimmed)) {
            return (string) ($existing ?? '');
        }

        return $trimmed;
    }
}
