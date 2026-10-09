<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SanitizeAndBlockAttacks
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only reject malformed routing paths. Evidence/narrative/credentials must
        // retain their exact values; escaping and typed validation belong at use sites.
        $path = $request->getPathInfo();
        for ($pass = 0; $pass < 3; $pass++) {
            $decoded = rawurldecode($path);
            if ($decoded === $path) break;
            $path = $decoded;
        }
        if (preg_match('#(?:^|[\\\\/])\.\.(?:[\\\\/]|$)|[\x00-\x1f\x7f\\\\]#', $path)) {
            // Paths, queries and headers can contain tokens and personal details.
            // Keep only a correlation fingerprint, never the attacker-controlled text.
            Log::warning('Security Alert: malformed request path blocked', [
                'method' => $request->method(),
                'path_hash' => hash('sha256', $path),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
            ]);
            return response()->json(['error' => 'Access Denied', 'message' => 'Invalid request path.'], 400);
        }
        if (preg_match('#(?:^|/)(?:uploads|storage)(?:/|$)#i', $path)) {
            // Defense in depth for front-controller/PHP development-server requests.
            return response()->json(['error' => 'Access Denied'], 403);
        }
        return $next($request);
    }
}
