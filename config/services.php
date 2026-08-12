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

    'lark' => [
        'app_id' => env('LARK_APP_ID'),
        'app_secret' => env('LARK_APP_SECRET'),
        'redirect_uri' => env('LARK_REDIRECT_URI'),
        'open_api_url' => env('LARK_OPEN_API_URL', 'https://open.larksuite.com'),
        'accounts_url' => env('LARK_ACCOUNTS_URL', 'https://accounts.larksuite.com'),
        'scope' => env('LARK_OAUTH_SCOPE', 'auth:user.id:read contact:user.email:readonly'),
        'verification_token' => env('LARK_VERIFICATION_TOKEN'),
        'encrypt_key' => env('LARK_ENCRYPT_KEY'),
        'bot_name' => env('LARK_BOT_NAME'),
        'default_group_prefix' => env('LARK_DEFAULT_GROUP_PREFIX'),
        'approval_enabled' => env('LARK_APPROVAL_ENABLED', false),
        'approval_outbound_enabled' => env('LARK_APPROVAL_OUTBOUND_ENABLED', false),
        'approval_name' => env('LARK_APPROVAL_NAME', 'Lark Modified'),
        'approval_admin_id' => env('LARK_APPROVAL_ADMIN_ID', '7383932003449585670'),
        'approval_code' => env('LARK_APPROVAL_CODE', 'E47A1D70-A980-4F01-AFAF-BFE2E4B3F69F'),
        'approval_field_map' => json_decode((string) env('LARK_APPROVAL_FIELD_MAP', '[]'), true) ?: [],
        'approval_node_approvers' => json_decode((string) env('LARK_APPROVAL_NODE_APPROVERS', '[]'), true) ?: [],
        'approval_url_template' => env('LARK_APPROVAL_URL_TEMPLATE'),
        'webhook_clock_skew' => (int) env('LARK_WEBHOOK_CLOCK_SKEW', 300),
    ],

];
