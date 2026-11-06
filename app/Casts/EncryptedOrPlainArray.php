<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Encrypts sensitive JSON settings at rest via AES-256-CBC while gracefully
 * reading legacy plaintext JSON rows if present.
 */
final class EncryptedOrPlainArray implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $raw = (string) $value;

        try {
            $decrypted = Crypt::decryptString($raw);
            $decoded = json_decode($decrypted, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        } catch (Throwable) {
            // Legacy plaintext JSON fallback
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $encoded = is_string($value) ? $value : (json_encode($value) ?: '{}');

        return Crypt::encryptString($encoded);
    }
}
