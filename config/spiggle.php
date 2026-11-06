<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | SpiggleMail Edition
    |--------------------------------------------------------------------------
    |
    | "core" — Self-hosted single-operator MIT edition (public signups disabled).
    |
    */
    'edition' => env('SPIGGLE_EDITION', 'core'),

    /*
    |--------------------------------------------------------------------------
    | Single-Operator Owner Email (Core Edition)
    |--------------------------------------------------------------------------
    |
    | When running in "core" edition, only this email address (or the initial
    | claimed operator if unset) is permitted to request or verify login OTPs.
    |
    */
    'owner_email' => env('OWNER_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Email MX DNS Verification
    |--------------------------------------------------------------------------
    |
    | Rejects domains with no valid MX/A mail exchanger records during public
    | subscription and CSV hygiene checks to prevent AWS SES hard bounces.
    |
    */
    'verify_email_mx' => (bool) env('VERIFY_EMAIL_MX', env('APP_ENV') !== 'testing'),
];
