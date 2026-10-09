<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Concerns\LocksWhenForwarded;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Requirement #2 — Role-Based Data Locking (controller-side guard).
 *
 * Returns a clear 423 "Locked" response when a line officer tries to mutate a
 * record they have already forwarded. This runs BEFORE the policy's own
 * forward-lock check, so the user gets an explanatory message instead of a bare
 * 403; the policy still denies independently (defence in depth).
 */
trait EnforcesStageLock
{
    /**
     * @param  object  $record  a model using the LocksWhenForwarded trait
     */
    protected function denyIfForwardLocked($record, ?Request $request = null): ?JsonResponse
    {
        if (! in_array(LocksWhenForwarded::class, class_uses_recursive($record), true)) {
            return null;
        }

        $user = ($request ?? request())->user();

        if (! $record->isLockedFor($user)) {
            return null;
        }

        return response()->json([
            'message' => 'This record has been forwarded to the next stage and is now read-only for you. '
                . 'You can still view it, but edits are locked. Contact your supervisor if a correction is needed.',
            'locked'  => true,
            'reason'  => 'stage_forwarded',
        ], 423);
    }
}
