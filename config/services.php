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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    // Stripe billing (Roadmap 4e). Use TEST keys (pk_test_… / sk_test_…) in development.
    // Leave empty until you have them: the Billing page then says "not connected yet".
    // Webhook secret: from `stripe listen --forward-to http://127.0.0.1:8080/api/stripe/webhook` (whsec_…).
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Per-business FBR credentials (NTN/STRN/token) live on the businesses table.
    // These two are the only globally-shared config: the FBR/PRAL API base URLs
    // per environment. Not yet verified against a real FBR sandbox — see
    // App\Services\FbrInvoiceService docblock before enabling in production.
    'fbr' => [
        'sandbox_url' => env('FBR_SANDBOX_URL'),
        'production_url' => env('FBR_PRODUCTION_URL'),
    ],

];
