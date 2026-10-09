<?php

return [
    // Enable after configuring a reCAPTCHA v2 checkbox key pair for this site.
    'enabled' => (bool) env('RECAPTCHA_ENABLED', false),
    'site_key' => env('RECAPTCHA_SITE_KEY', ''),
    'secret_key' => env('RECAPTCHA_SECRET_KEY', ''),
    'allowed_hostnames' => array_values(array_filter(array_map('trim', explode(',',
        (string) env('RECAPTCHA_ALLOWED_HOSTNAMES', parse_url(env('APP_URL', ''), PHP_URL_HOST) ?: '')
    )))),
];
