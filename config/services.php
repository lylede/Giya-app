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
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Groq
    |--------------------------------------------------------------------------
    | Powers the Giya AI assistant. Free key: https://console.groq.com/keys
    | Without a key the assistant answers from the database alone and says so.
    */
    'groq' => [
        'key'   => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maya (PayMaya) - Checkout
    |--------------------------------------------------------------------------
    | GIYA never handles card numbers. The devotee is sent to Maya's own hosted
    | checkout page, pays there, and comes back. Two keys, and they are not
    | interchangeable:
    |
    |   public  - creates a checkout. Safe to send in a request the devotee
    |             could in principle observe; it can only start a payment.
    |   secret  - reads a payment's real status and registers webhooks. This
    |             one must never reach the browser or a public repository.
    |
    | Sandbox keys are the published test pair from Maya's documentation, so a
    | teammate can clone the repo and pay with a test card without waiting for
    | credentials. Live keys go in .env only.
    */
    'maya' => [
        'base_url' => env('MAYA_BASE_URL', 'https://pg-sandbox.paymaya.com'),
        'public'   => env('MAYA_PUBLIC_KEY',  'pk-Z0OSzLvIcOI2UIvDhdTGVVfRSSeiGStnceqwUE7n0Ah'),
        'secret'   => env('MAYA_SECRET_KEY',  'sk-X8qolYjy62kIzEbr0QRK1h4b4KDVHaNcwMYk39jInSl'),

        // Maya posts webhooks from a fixed set of addresses and signs nothing,
        // so the address is the only cheap filter available. It is a filter,
        // not the security boundary - the payment is still re-fetched.
        'webhook_ips' => [
            '13.229.160.234', '3.1.199.75',       // sandbox
            '18.138.50.235',  '3.1.207.200',      // production
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
