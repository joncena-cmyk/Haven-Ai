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

    // HAVEN ML Backend (FastAPI)
    'ml_backend' => [
        'url'     => env('ML_BACKEND_URL', 'http://localhost:8001'),
        'timeout' => env('ML_BACKEND_TIMEOUT', 30),
        'secret'  => env('ML_API_SECRET', ''),
    ],

    // NHTSA vPIC vehicle catalog (free, no key)
    'nhtsa' => [
        'url'     => env('NHTSA_VPIC_URL', 'https://vpic.nhtsa.dot.gov/api/vehicles'),
        'timeout' => env('NHTSA_VPIC_TIMEOUT', 20),
    ],

    // HAVEN AI Assistant LLM Providers (Optional - built-in offline engine active by default)
    'gemini' => [
        'key'   => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
    ],

    'openai' => [
        'key'   => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'groq' => [
        'key'   => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    ],

];
