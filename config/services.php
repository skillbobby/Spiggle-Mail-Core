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

    'polar' => [
        'token' => env('POLAR_ACCESS_TOKEN'),
        'webhook_secret' => env('POLAR_WEBHOOK_SECRET'),
        'organization_id' => env('POLAR_ORGANIZATION_ID', '494f9913-fd1e-461c-8fa1-c3f0b3740dcf'),
        'meter_id' => env('POLAR_METER_ID', '1a92c493-3fc2-4375-abec-570c2750f52d'),
        'products' => [
            'starter_month' => env('POLAR_PRODUCT_STARTER_MONTH', 'e78f0986-7770-48e2-a8bb-2d7922cd1dff'),
            'starter_year' => env('POLAR_PRODUCT_STARTER_YEAR', 'aead0880-c08f-4bdc-9c30-e51697fd1e00'),
            'growth_month' => env('POLAR_PRODUCT_GROWTH_MONTH', '3a1b98d6-ca37-4861-ad69-6856d6b10188'),
            'growth_year' => env('POLAR_PRODUCT_GROWTH_YEAR', 'bfe46e80-d44f-4b80-9929-8f20a12633a2'),
            'scale_month' => env('POLAR_PRODUCT_SCALE_MONTH', '48aff0ef-3623-4a5b-980d-7407a75ca506'),
            'scale_year' => env('POLAR_PRODUCT_SCALE_YEAR', '1bfd238e-5ba1-43d9-9beb-636d1d70630e'),
        ],
    ],

];
