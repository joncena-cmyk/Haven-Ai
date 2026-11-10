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
        'url' => env('ML_BACKEND_URL', env('ML_API_URL', 'http://127.0.0.1:8001')),
        'timeout' => env('ML_BACKEND_TIMEOUT', 30),
        'secret' => env('ML_API_SECRET', ''),
    ],

    // NHTSA vPIC vehicle catalog (free, no key)
    'nhtsa' => [
        'url' => env('NHTSA_VPIC_URL', 'https://vpic.nhtsa.dot.gov/api/vehicles'),
        'timeout' => env('NHTSA_VPIC_TIMEOUT', 20),
    ],

    // HAVEN AI Assistant LLM Providers (Optional - built-in offline engine active by default)
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'sms_sender' => env('BREVO_SMS_SENDER', 'HavenAI'),
        'from_email' => env('BREVO_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'noreply@haven.local')),
        'from_name' => env('BREVO_MAIL_FROM_NAME', env('APP_NAME', 'Haven AI')),
    ],

    'firebase' => [
        'api_key' => env('FIREBASE_API_KEY'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'app_id' => env('FIREBASE_APP_ID'),
    ],

    // Google Sign-In (OAuth 2.0). Redirect URI to register in Google Cloud Console:
    //   {APP_URL}/auth/google/callback
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost:8000'), '/').'/auth/google/callback'),
    ],

    // Google Places API (New) — nearby repair shops. Server-side key (restrict by API, not by referrer).
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
        'radius_m' => (int) env('GOOGLE_MAPS_RADIUS_M', 10000),
    ],

    // Google Cloud Speech-to-Text & Text-to-Speech (REST, API-key auth, server-side only).
    'google_cloud' => [
        'key' => env('GOOGLE_CLOUD_API_KEY'),
        'stt_language' => env('GOOGLE_STT_LANGUAGE', 'en-US'),
        'tts_voice' => env('GOOGLE_TTS_VOICE', 'en-US-Neural2-D'),
        'tts_language' => env('GOOGLE_TTS_LANGUAGE', 'en-US'),
    ],

];
