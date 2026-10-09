<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Requirement #3 — data integrity: optimistic concurrency control.
 *
 * Without this, if two officers open the same record and both save, the second
 * write silently clobbers the first ("lost update"). This guard lets the client
 * send the `updated_at` it originally loaded (as the `expected_updated_at` field
 * or an `If-Unmodified-Since` header). If the stored record is newer, the save
 * is rejected with 409 Conflict and the client is told to reload first.
 *
 * It is OPT-IN: a request that sends no token behaves exactly as before, so the
 * change is non-breaking. Combined with the immutable audit ledger, every actual
 * write remains fully traceable.
 */
trait PreventsLostUpdates
{
    protected function denyIfStale($record, ?Request $request = null): ?JsonResponse
    {
        $request = $request ?? request();

        $expected = $request->input('expected_updated_at')
            ?: $request->header('If-Unmodified-Since');

        // No token supplied → optimistic check is opt-in, behave as before.
        if (! $expected || ! $record->updated_at) {
            return null;
        }

        try {
            $expectedAt = Carbon::parse($expected);
        } catch (\Throwable) {
            return null; // unparseable token → don't block the save
        }

        // Stored record is strictly newer than what the client loaded → conflict.
        if ($record->updated_at->gt($expectedAt)) {
            return response()->json([
                'message' => 'This record was changed by someone else while you were editing it. '
                    . 'Reload to get the latest version, then re-apply your changes.',
                'conflict'           => true,
                'current_updated_at' => $record->updated_at->toIso8601String(),
            ], 409);
        }

        return null;
    }
}
