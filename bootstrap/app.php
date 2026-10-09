<?php

// Ensure classes from this workspace take precedence over shared vendor baseDir
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    } elseif (str_starts_with($class, 'Database\\Seeders\\')) {
        $file = __DIR__ . '/../database/seeders/' . str_replace('\\', '/', substr($class, 17)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    } elseif (str_starts_with($class, 'Database\\Factories\\')) {
        $file = __DIR__ . '/../database/factories/' . str_replace('\\', '/', substr($class, 19)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
}, true, true);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // ── Global pipeline (web + api) ─────────────────────────────────────
        // SecurityHeaders must run on every response to set X-Frame-Options, CSP, etc.
        $middleware->prepend(\App\Http\Middleware\SecurityHeaders::class);

        // SanitizeAndBlockAttacks blocks XSS/SQLi/LFI/SSTI/path-traversal on all requests.
        $middleware->append(\App\Http\Middleware\SanitizeAndBlockAttacks::class);

        // BlockDirectApiNavigation prevents direct browser address-bar navigation to raw
        // API endpoints. Must be global so it can redirect before any auth check.
        $middleware->append(\App\Http\Middleware\BlockDirectApiNavigation::class);

        // IdleTimeout uses session state — register it only in the web (stateful) group
        // so it never runs on stateless Sanctum token requests where no session exists.
        $middleware->web(append: [\App\Http\Middleware\IdleTimeout::class]);

        // Run type, size and malware checks before controllers persist any uploads.
        // Priority puts protected uploads after authentication/authorization and throttling.
        $middleware->web(append: [\App\Http\Middleware\InspectUploads::class]);
        $middleware->api(append: [\App\Http\Middleware\InspectUploads::class]);
        $middleware->appendToPriorityList(\Illuminate\Auth\Middleware\Authorize::class, \App\Http\Middleware\InspectUploads::class);

        // ── Named middleware aliases ─────────────────────────────────────────
        $middleware->alias([
            'throttle.api' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'role'         => \App\Http\Middleware\CheckRole::class,
            'account.security' => \App\Http\Middleware\EnforceAccountSecurity::class,
        ]);

        // ── CSRF exceptions ──────────────────────────────────────────────────
        // Exempt /api/logout so users whose CSRF cookie has expired can still
        // log out cleanly instead of being trapped by HTTP 419 Page Expired.
        // All data-mutating routes (complaints, enquiries, cases, users) remain
        // fully protected — only the session-terminating logout is exempted.
        $middleware->validateCsrfTokens(except: [
            'api/logout',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Exceptions unwind the middleware stack; decorate those responses too.
        $exceptions->respond(fn (\Symfony\Component\HttpFoundation\Response $response) =>
            app(\App\Http\Middleware\SecurityHeaders::class)->applyHeaders(request(), $response)
        );
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
