<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

class ComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        // Investigation officers use case workflow, not the complaints module.
        if ($user->hasRole('investigation_officer') && !$user->hasAnyRole(['admin', 'circle_incharge', 'operator'])) {
            return false;
        }

        // VO may read assigned complaints (scoped by visibleTo) for verification work.
        return true;
    }

    public function view(User $user, Complaint $complaint): bool
    {
        if ($user->hasRole('investigation_officer') && !$user->hasAnyRole(['admin', 'circle_incharge', 'operator'])) {
            return false;
        }

        return Complaint::visibleTo($user)->whereKey($complaint->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'circle_incharge', 'operator', 'director_general']);
    }

    public function update(User $user, Complaint $complaint): bool
    {
        // Verification officers must not edit complaints.
        if ($user->hasRole('verification_officer') && !$user->hasAnyRole(['admin', 'circle_incharge', 'operator'])) {
            return false;
        }

        if ($user->hasRole('investigation_officer') && !$user->hasAnyRole(['admin', 'circle_incharge', 'operator'])) {
            return false;
        }

        // Requirement #2 — once forwarded to verification, the line officer
        // (operator) who registered it keeps read access only. Admin / DG
        // retain an audited correction path (isLockedFor returns false for them).
        if ($complaint->isLockedFor($user)) {
            return false;
        }

        if ($user->hasRole('admin') || $user->hasRole('director_general')) {
            return true;
        }

        if ($user->hasRole('circle_incharge')) {
            return !$user->circle_id || (int) $complaint->circle_id === (int) $user->circle_id;
        }

        if ($user->hasRole('operator')) {
            return (int) $user->id === (int) $complaint->user_id
                || (int) $user->id === (int) $complaint->operator_id;
        }

        return false;
    }

    public function delete(User $user, Complaint $complaint): bool
    {
        return $user->hasRole('admin');
    }
}
