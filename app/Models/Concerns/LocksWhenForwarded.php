<?php

namespace App\Models\Concerns;

use App\Models\User;

/**
 * Requirement #2 — Role-Based Data Locking.
 *
 * A workflow record (Complaint, Verification, Enquiry, CaseFile) passes through
 * a chain of officers. The moment the owning officer FORWARDS it to the next
 * stage, their contribution is frozen: they keep read access but lose the right
 * to edit. The next officer can view everything but cannot edit entries made in
 * the prior stages (ownership checks in each policy already enforce the
 * cross-officer half; this trait enforces the forward-lock half).
 *
 * A model using this trait declares the statuses that mean "forwarded":
 *
 *     protected array $forwardLockStatuses = ['submitted', 'approved', ...];
 *
 * Supervisors with correction authority (admin / DG) are never locked out, but
 * every correction they make is captured by the immutable audit ledger.
 */
trait LocksWhenForwarded
{
    /**
     * Statuses at which the record has moved beyond the editing officer.
     */
    public function forwardedStatuses(): array
    {
        return property_exists($this, 'forwardLockStatuses')
            ? $this->forwardLockStatuses
            : [];
    }

    /**
     * Has this record been forwarded past the stage that created it?
     */
    public function isForwarded(): bool
    {
        $status = strtolower(trim((string) ($this->status ?? '')));

        if ($status === '') {
            return false;
        }

        return in_array($status, array_map('strtolower', $this->forwardedStatuses()), true);
    }

    /**
     * Is editing locked for this specific user?
     *
     * Locked ⇔ the user is a line officer (not a supervisor) AND the record has
     * been forwarded. Supervisors keep an audited correction path. A null user
     * is always treated as locked (fail-closed).
     */
    public function isLockedFor(?User $user): bool
    {
        if (! $user) {
            return true;
        }

        if ($user->canOverrideStageLock()) {
            return false;
        }

        return $user->isLineOfficer() && $this->isForwarded();
    }
}
