<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockDirectApiNavigation
{
    /**
     * Prevent direct browser address bar visits to raw API endpoints.
     * Legitimate AJAX / Axios / Mobile calls use Sec-Fetch-Dest: empty or send JSON headers.
     */
    /**
     * GET endpoints that are MEANT to be opened directly in a browser tab
     * (official print / export / download views). These must not be treated
     * as "direct API navigation", or the print tab gets redirected to home.
     */
    private function isOpenableDocument(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        return $request->is(
            'api/secure-file',       // authenticated image/file serving (loaded in <img>)
            'api/*/export',          // DSR & DO letter official views / Excel
            'api/*/pdf',             // any PDF download endpoint
            'api/*-print',           // notice / warrant / diary print views
            'api/*/slip',            // complaint slip
            'api/*/report'           // complaint report
        );
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api', 'api/*', 'sanctum/*')) {
            // Allow legitimate "open in new tab" document/print/export endpoints.
            if ($this->isOpenableDocument($request)) {
                return $next($request);
            }

            $dest = $request->header('Sec-Fetch-Dest');
            $mode = $request->header('Sec-Fetch-Mode');

            // 1. Direct browser address bar navigation or iframe embedding strictly blocked
            if (in_array($dest, ['document', 'iframe', 'frame'], true) || in_array($mode, ['navigate', 'nested-navigate'], true)) {
                return redirect('/');
            }

            // 2. Direct browser GET requests without JSON / AJAX headers blocked
            if ($request->isMethod('GET') && ! $request->expectsJson() && ! $request->ajax() && ! $request->bearerToken()) {
                return redirect('/');
            }
        }

        return $next($request);
    }
}
