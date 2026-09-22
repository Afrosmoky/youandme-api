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

        // Sign in with Apple key, used only to revoke our tokens when an account
        // is deleted (App Store 5.1.1(v)). Team ID and Key ID come from the Apple
        // developer portal (Keys → the Sign in with Apple key).
        //
        // private_key_path points at the downloaded AuthKey_<KEYID>.p8. On the
        // server the file lives OUTSIDE the application directory (so no deploy,
        // backup of the release or `git add` can pick it up), is chmod 600 and is
        // owned by the user PHP-FPM runs as. Never commit it and never paste its
        // contents into .env. Relative paths resolve from the project root, which
        // is for local development only.
        //
        // Any of the three unset means "no revocation": the deletion still goes
        // through and a warning is logged. Revocation switches on with a .env
        // change alone, no app build needed.
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key_path' => env('APPLE_PRIVATE_KEY_PATH'),
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
