<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class CaptchaController extends Controller
{
    public function __invoke()
    {
        $enabled = (bool) config('recaptcha.enabled');
        abort_if($enabled && (!config('recaptcha.site_key') || !config('recaptcha.secret_key')
            || !config('recaptcha.allowed_hostnames')), 503, 'Human verification is unavailable.');

        return response()->json([
            'enabled' => $enabled,
            'site_key' => $enabled ? config('recaptcha.site_key') : null,
        ])->header('Cache-Control', 'no-store');
    }
}
