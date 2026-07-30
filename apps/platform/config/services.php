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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // AMD-0017 : pilote de dépôt Wallet en Côte d'Ivoire. Aucun secret
    // codé en dur (EXE-0001 §5) — `api_key`/`api_secret`/`webhook_secret`
    // doivent être fournis par l'environnement de production ; en leur
    // absence, `HttpGeniusPayClient` échoue proprement (403 côté
    // prestataire) plutôt que d'inventer une valeur.
    'geniuspay' => [
        'base_url' => env('GENIUSPAY_BASE_URL', 'http://pay.genius.ci/api/v1/merchant'),
        'api_key' => env('GENIUSPAY_API_KEY'),
        'api_secret' => env('GENIUSPAY_API_SECRET'),
        'webhook_secret' => env('GENIUSPAY_WEBHOOK_SECRET'),
    ],

];
