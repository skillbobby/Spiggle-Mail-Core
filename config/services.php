<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
        'webhook_secret' => env('POSTMARK_WEBHOOK_SECRET'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'driver' => env('SES_DRIVER', 'mock'),
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'configuration_set' => env('AWS_SES_CONFIGURATION_SET'),
        'verify_sns_signature' => (bool) env('AWS_SES_VERIFY_SNS_SIGNATURE', env('APP_ENV') !== 'testing'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tickitdex' => [
        'enabled' => env('TICKITDEX_ENABLED', false),
        'token' => env('TICKITDEX_TOKEN'),
        'webhook_url' => env('TICKITDEX_WEBHOOK_URL', 'https://tickitdex.com/support-chat/webhooks/tickets'),
        'default_category' => env('TICKITDEX_CATEGORY', 'deliverability'),
        'customer_email' => env('TICKITDEX_CUSTOMER_EMAIL'),
        'timeout' => env('TICKITDEX_TIMEOUT', 5),
    ],

    'telegram' => [
        'enabled' => env('TELEGRAM_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

];
