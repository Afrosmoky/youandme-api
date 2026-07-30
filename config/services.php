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
        'key' => env('RESEND_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'apple' => [
        // iOS app bundle ID; the `aud` claim of Apple ID tokens.
        'client_id' => env('APPLE_CLIENT_ID'),
    ],

    // Firebase Cloud Messaging (server push, P7). One setting: the path to the
    // service account JSON downloaded from the Firebase console (project_id,
    // client_email and private_key are read out of it). A path rather than three
    // env values keeps a multi-line PEM out of .env entirely — the file is
    // gitignored under storage/. Relative paths resolve from the project root.
    //
    // Unset or unreadable means "no pushes": logged and skipped, never fatal.
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS'),
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

];
