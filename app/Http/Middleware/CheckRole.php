<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            abort(401);
        }

        // Admin superuser always has access
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        if (!$user->hasAnyRole($roles)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized - insufficient permissions'], 403);
            }
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
