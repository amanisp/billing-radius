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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mpwa' => [
        'api_key' => env('MPWA_API_KEY', 'qnARDI9SJswXG7Vl6e3EFyMUCrq1v4'),
        'base_url' => env('MPWA_BASE_URL', 'https://mpwa.amanisp.net.id'),
        'sender' => env('MPWA_DEFAULT_SENDER', '62895380015903'),
    ],

    'freeradius' => [
        'url' => env('FREERADIUS_API_URL'),
        'token' => env('FREERADIUS_API_TOKEN'),
    ],

];
