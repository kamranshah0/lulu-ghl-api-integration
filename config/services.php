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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Lulu Print API
    |--------------------------------------------------------------------------
    */
    'lulu' => [
        'client_key'      => env('LULU_CLIENT_KEY'),
        'client_secret'   => env('LULU_CLIENT_SECRET'),
        'use_sandbox'     => env('LULU_USE_SANDBOX', true),
        'api_base'        => env('LULU_API_BASE', 'https://api.lulu.com'),
        'sandbox_api_base' => env('LULU_SANDBOX_API_BASE', 'https://api.sandbox.lulu.com'),
        'contact_email'   => env('LULU_CONTACT_EMAIL'),
        'book_interior_url' => env('LULU_BOOK_INTERIOR_URL'),
        'book_cover_url'  => env('LULU_BOOK_COVER_URL'),
        'pod_package_id'  => env('LULU_POD_PACKAGE_ID'),
        'shipping_level'  => env('LULU_SHIPPING_LEVEL', 'MAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GoHighLevel (GHL)
    |--------------------------------------------------------------------------
    */
    'ghl' => [
        'webhook_secret' => env('GHL_WEBHOOK_SECRET'),
        'api_key'        => env('GHL_API_KEY'),
        'location_id'    => env('GHL_LOCATION_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Credentials
    |--------------------------------------------------------------------------
    */
    'admin' => [
        'email'    => env('ADMIN_EMAIL', 'admin@foreverwellthy.com'),
        'password' => env('ADMIN_PASSWORD', 'changeme123'),
    ],

];
