
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

    'mux' => [
        'token_id' => env('MUX_TOKEN_ID'),
        'token_secret' => env('MUX_TOKEN_SECRET'),
        'signing_key_id' => env('MUX_SIGNING_KEY_ID'),
        'signing_private_key' => env('MUX_SIGNING_PRIVATE_KEY'),
        'live' => [
            'ingest_url' => env('MUX_LIVE_INGEST_URL', 'rtmps://global-live.mux.com:443/app'),
            'reconnect_window' => (int) env('MUX_LIVE_RECONNECT_WINDOW', 60),
        ],
        'timeout' => [
            'connect' => (int) env('MUX_CONNECT_TIMEOUT', 5),
            'request' => (int) env('MUX_REQUEST_TIMEOUT', 10),
        ],
    ],

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

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'api_key' => env('TWILIO_API_KEY'),
        'api_secret' => env('TWILIO_API_SECRET'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'video' => [
            'room_type' => env('TWILIO_VIDEO_ROOM_TYPE', 'group'),
            'token_ttl' => (int) env('TWILIO_VIDEO_TOKEN_TTL', 21600),
        ],
    ],



    'google_document_ai' => [
        'project_id' => env('GOOGLE_DOCUMENT_AI_PROJECT_ID'),
        'location' => env('GOOGLE_DOCUMENT_AI_LOCATION', 'eu'),
        'processor_id' => env('GOOGLE_DOCUMENT_AI_PROCESSOR_ID'),
        'credentials' => env('GOOGLE_DOCUMENT_AI_CREDENTIALS'),
        'access_token' => env('GOOGLE_DOCUMENT_AI_ACCESS_TOKEN'),
        'timeout' => [
            'connect' => (int) env('GOOGLE_DOCUMENT_AI_CONNECT_TIMEOUT', 5),
            'request' => (int) env('GOOGLE_DOCUMENT_AI_REQUEST_TIMEOUT', 30),
        ],
    ],

];
