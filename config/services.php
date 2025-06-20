<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payment' => [
        'default_gateway' => env('DEFAULT_PAYMENT_GATEWAY', 'stripe'),
    ],

//    'stripe' => [
//        'key' => env('STRIPE_KEY'),
//        'secret' => env('STRIPE_SECRET'),
//        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
//    ],

//    'paypal' => [
//        'client_id' => env('PAYPAL_CLIENT_ID'),
//        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
//        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
//        'sandbox' => env('PAYPAL_SANDBOX', true),
//        'verify_webhook_signature' => env('PAYPAL_VERIFY_WEBHOOK_SIGNATURE', true),
//    ],
    'stripe' => [
        'model' => App\Models\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'endpoint_secret' => env('STRIPE_ENDPOINT_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'automatic_tax' => env('STRIPE_AUTOMATIC_TAX', false),
    ],

    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox or live
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'verify_webhook_signature' => env('PAYPAL_VERIFY_WEBHOOK_SIGNATURE', true),
    ],

    'subscription' => [
        'default' => env('DEFAULT_SUBSCRIPTION_PROVIDER', 'paypal'),
    ],
];
