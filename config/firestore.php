<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Firestore Project ID
    |--------------------------------------------------------------------------
    |
    | This value determines the default project ID that will be used when
    | connecting to Google Firestore. You may set this to any project ID
    | registered with your Google Cloud account.
    |
    */
    'project_id' => env('FIRESTORE_PROJECT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Google Application Credentials
    |--------------------------------------------------------------------------
    |
    | The path to your Google Cloud service account key file. This file contains
    | your project credentials and should be kept secure. If not provided, the
    | package will attempt to use the default application credentials.
    |
    */
    'key_file_path' => env('FIRESTORE_KEY_FILE', null),

    /*
    |--------------------------------------------------------------------------
    | Firestore Database Options
    |--------------------------------------------------------------------------
    |
    | Additional configuration options for the Firestore client.
    |
    */
    'options' => [
        'emulator' => [
            'enabled' => env('FIRESTORE_EMULATOR_ENABLED', false),
            'host' => env('FIRESTORE_EMULATOR_HOST', 'localhost'),
            'port' => env('FIRESTORE_EMULATOR_PORT', 8080),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Token Caching
    |--------------------------------------------------------------------------
    |
    | These settings control whether and how long to cache the authentication
    | token to reduce API calls to Google's authentication servers.
    |
    */
    'use_token_cache' => env('FIRESTORE_USE_TOKEN_CACHE', true),
    
    /*
    |--------------------------------------------------------------------------
    | Token Cache Duration
    |--------------------------------------------------------------------------
    |
    | How long to cache the authentication token in seconds.
    | Default is 3500 seconds (just under 1 hour, as Google tokens typically
    | expire after 1 hour).
    |
    */
    'token_cache_time' => env('FIRESTORE_TOKEN_CACHE_TIME', 3500),
];
