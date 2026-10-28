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
    |
    | Groq retires models. llama-3.3-70b-versatile was shut down on Aug 16,
    | 2026 and every question then fell back to "offline". If that happens
    | again, laravel.log says "Groq model ... is not available"; pick a current
    | model from https://console.groq.com/docs/models and set GROQ_MODEL.
    */
    'groq' => [
        'key'   => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
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
    | Keys come from .env only - there are deliberately no defaults here. A
    | default meant a production server with a missing MAYA_SECRET_KEY quietly
    | ran on the sandbox pair, taking "payments" that were never real.
    | .env.example carries Maya's published sandbox pair, so a teammate who
    | copies it can still pay with a test card straight away.
    */
    'maya' => [
        'base_url' => env('MAYA_BASE_URL', 'https://pg-sandbox.paymaya.com'),
        'public'   => env('MAYA_PUBLIC_KEY', ''),
        'secret'   => env('MAYA_SECRET_KEY', ''),

        // Maya posts webhooks from a fixed set of addresses and signs nothing,
        // so the address is the only cheap filter available. It is a filter,
        // not the security boundary - the payment is still re-fetched.
        //
        // Behind ngrok, Cloudflare or a load balancer, set TRUSTED_PROXIES in
        // .env (see bootstrap/app.php) or every webhook arrives from the
        // proxy's address and is dropped. MAYA_WEBHOOK_IPS="" turns the
        // filter off entirely - the re-fetch still protects the payment.
        'webhook_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'MAYA_WEBHOOK_IPS',
            '13.229.160.234,3.1.199.75,18.138.50.235,3.1.207.200'   // sandbox x2, production x2
        ))))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supabase Storage - uploaded photos on the hosted app
    |--------------------------------------------------------------------------
    | Vercel and Render wipe the app's own disk on every restart, so a photo
    | saved there would vanish. When these are set, new church photos and
    | profile pictures go to a public Supabase Storage bucket instead (see
    | App\Support\Uploads). Left blank - as on a laptop - uploads are saved
    | locally exactly as before.
    |
    | url:    https://<project-ref>.supabase.co
    | key:    the project's SECRET / service_role key (server-side only)
    | bucket: a PUBLIC bucket, created in the Supabase dashboard
    */
    'supabase' => [
        'url'    => env('SUPABASE_URL'),
        'key'    => env('SUPABASE_SERVICE_KEY'),
        'bucket' => env('SUPABASE_BUCKET', 'giya-uploads'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
