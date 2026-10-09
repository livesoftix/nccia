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

    'adp' => [
        'url' => rtrim(env('ADP_API_URL', 'http://127.0.0.1:8001'), '/'),
        'timeout' => (int) env('ADP_API_TIMEOUT', 120),
        'allowed_hosts' => array_filter(array_map('trim', explode(',', (string) env('ADP_ALLOWED_HOSTS', '127.0.0.1')))),
        'allowed_ports' => array_map('intval', explode(',', (string) env('ADP_ALLOWED_PORTS', '443,8001'))),
        'allow_local_http' => (bool) env('ADP_ALLOW_LOCAL_HTTP', false),
        'allow_private_network' => (bool) env('ADP_ALLOW_PRIVATE_NETWORK', false),
        'approved_disclosure' => (bool) env('ADP_APPROVED_DISCLOSURE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway (Pakistani providers: Jazz/Telenor/Ufone/Zong via HTTP API)
    |--------------------------------------------------------------------------
    |
    | Build the service to accept either GET or POST based on the gateway.
    | `sms.http_method`  : get|post
    | `sms.params`       : query/body params the gateway expects, with {phone},
    |                      {message} and {sender} placeholders. Extra credentials
    |                      (api_key, username, password, route_id, etc.) go here.
    | `sms.success_match` : substring expected in a successful response
    |                       (e.g. "ok", "1", "success"). Empty disables checking.
    |
    */
    'sms' => [
        'enabled'        => filter_var(env('SMS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'provider'       => env('SMS_PROVIDER', 'generic'),
        'gateway_url'    => env('SMS_API_URL', ''),
        'http_method'    => strtolower(env('SMS_HTTP_METHOD', 'post')),
        'sender_id'      => env('SMS_SENDER_ID', 'NCCIA'),
        'success_match'  => env('SMS_SUCCESS_MATCH', ''),
        'timeout'        => (int) env('SMS_TIMEOUT', 15),
        'allowed_hosts' => array_filter(array_map('trim', explode(',', (string) env('SMS_ALLOWED_HOSTS', '')))),
        'allowed_ports' => array_map('intval', explode(',', (string) env('SMS_ALLOWED_PORTS', '443'))),
        'params'         => [
            'api_key'  => env('SMS_API_KEY', ''),
            'username' => env('SMS_USERNAME', ''),
            'password' => env('SMS_PASSWORD', ''),
            'route_id' => env('SMS_ROUTE_ID', ''),
        ],
    ],

];
