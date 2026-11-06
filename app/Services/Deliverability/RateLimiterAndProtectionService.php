<?php

declare(strict_types=1);

namespace App\Services\Deliverability;

final class RateLimiterAndProtectionService
{
    // Threshold constants
    public const PAUSE_BOUNCE_RATE = 0.02;      // 2% bounce rate triggers auto-pause

    public const PAUSE_MIN_BOUNCES = 5;

    public const PAUSE_COMPLAINT_RATE = 0.0008; // 0.08% complaint rate triggers auto-pause

    public const PAUSE_MIN_COMPLAINTS = 2;

    public const BLOCK_BOUNCE_RATE = 0.05;      // 5% bounce rate triggers account lock

    public const BLOCK_MIN_BOUNCES = 10;

    public const BLOCK_COMPLAINT_RATE = 0.003;  // 0.3% complaint rate triggers account lock

    public const BLOCK_MIN_COMPLAINTS = 3;

    public const MAX_EMAIL_BYTES = 100_000;     // 100 KB clip detection for Gmail

    /**
     * Evaluates campaign sending metrics and determines if sending should be paused or blocked.
     *
     * @return array{action: 'block'|'pause'|'ok', metric: 'bounce'|'complaint'|null, rate: float, limit: float}
     */
    public static function evaluateSafety(int $sent, int $bounced, int $complained): array
    {
        if ($sent <= 0) {
            return ['action' => 'ok', 'metric' => null, 'rate' => 0.0, 'limit' => 0.0];
        }

        $bounceRate = $bounced / $sent;
        $complaintRate = $complained / $sent;

        // 1. Check Hard Blocks first
        if ($bounced >= self::BLOCK_MIN_BOUNCES && $bounceRate >= self::BLOCK_BOUNCE_RATE) {
            return ['action' => 'block', 'metric' => 'bounce', 'rate' => $bounceRate, 'limit' => self::BLOCK_BOUNCE_RATE];
        }

        if ($complained >= self::BLOCK_MIN_COMPLAINTS && $complaintRate >= self::BLOCK_COMPLAINT_RATE) {
            return ['action' => 'block', 'metric' => 'complaint', 'rate' => $complaintRate, 'limit' => self::BLOCK_COMPLAINT_RATE];
        }

        // 2. Check Auto-Pauses
        if ($bounced >= self::PAUSE_MIN_BOUNCES && $bounceRate >= self::PAUSE_BOUNCE_RATE) {
            return ['action' => 'pause', 'metric' => 'bounce', 'rate' => $bounceRate, 'limit' => self::PAUSE_BOUNCE_RATE];
        }

        if ($complained >= self::PAUSE_MIN_COMPLAINTS && $complaintRate >= self::PAUSE_COMPLAINT_RATE) {
            return ['action' => 'pause', 'metric' => 'complaint', 'rate' => $complaintRate, 'limit' => self::PAUSE_COMPLAINT_RATE];
        }

        return ['action' => 'ok', 'metric' => null, 'rate' => 0.0, 'limit' => 0.0];
    }

    /**
     * Hash email address with secret key for cross-account global suppression checking without storing raw PII.
     */
    public static function hashSuppression(string $email, string $key): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), $key);
    }
}
