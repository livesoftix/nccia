<?php

namespace App\Http\Middleware;

use App\Exceptions\AuditUnavailable;
use App\Services\AuditLedgerService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Database mutations and their audit records commit together or roll back together. */
class AuditedMutation
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || !config('security.audit.require_writes')) {
            return $next($request);
        }
        $response = null;
        $request->attributes->set('audit_write_required', true);
        $request->attributes->set('audit_write_failed', false);
        try {
            return DB::transaction(function () use ($request, $next, &$response) {
                AuditLedgerService::assertWritable();
                $response = $next($request);
                if ($request->attributes->get('audit_write_failed') || $response->getStatusCode() >= 400) {
                    throw new AuditUnavailable('Database mutation must be rolled back.');
                }
                AuditLedgerService::record('mutation.completed', ['properties' => [
                    'method' => $request->method(), 'route' => $request->route()?->uri() ?? 'unknown',
                ]]);
                return $response;
            });
        } catch (AuditUnavailable) {
            if ($response && $response->getStatusCode() >= 400 && !$request->attributes->get('audit_write_failed')) {
                return $response;
            }
            return response()->json(['message' => 'The action could not be securely recorded. Try again or contact support.'], 503);
        } finally {
            $request->attributes->remove('audit_write_required');
            $request->attributes->remove('audit_write_failed');
        }
    }
}
