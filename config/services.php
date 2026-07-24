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

    'ldap' => [
        'connection' => env('LDAP_CONNECTION', 'ldap'),
        'host' => env('LDAP_HOST', '127.0.0.1'),
        'port' => env('LDAP_PORT', 389),
        'base_dn' => env('LDAP_BASE_DN'),
        'user_domain' => env('LDAP_USER_DOMAIN'),
        'group' => env('LDAP_GROUP'),
    ],

    'qiscus' => [
        'app_id' => env('QISCUS_APP_ID'),
        'secret_key' => env('QISCUS_SECRET_KEY'),
        'channel_id' => env('QISCUS_WA_CHANNEL_ID'),
        'base_url' => env('QISCUS_API_BASE_URL', 'https://multichannel.qiscus.com'),
    ],

];
