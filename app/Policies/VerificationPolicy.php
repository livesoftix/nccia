<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Verification;
use Illuminate\Auth\Access\HandlesAuthorization;

class VerificationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Verification $verification): bool
    {
        return Verification::visibleTo($user)->whereKey($verification->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'circle_incharge', 'operator', 'verification_officer', 'director_general']);
    }

    public function update(User $user, Verification $verification): bool
    {
        if ($user->hasRole('admin') || $user->seesAllData()) {
            return true;
        }

        if ($user->hasRole('circle_incharge')) {
            return $this->sameCircle($user, $verification);
        }

        if ($user->hasAnyRole(['verification_officer', 'investigation_officer'])) {
            // Requirement #2 — once submitted/forwarded, the owning officer
            // keeps read access only (sent_back re-opens it for rework).
            if ($verification->isLockedFor($user)) {
                return false;
            }

            return (int) $user->id === (int) $verification->verification_officer_id;
        }

        return false;
    }

    public function delete(User $user, Verification $verification): bool
    {
        return $user->hasRole('admin');
    }

    public function approve(User $user, Verification $verification): bool
    {
        if ($user->hasRole('admin') || $user->seesAllData()) {
            return true;
        }

        if (!$user->hasRole('circle_incharge')) {
            return false;
        }

        // Allow if same circle OR verification is already in CI's visible scope
        if ($this->sameCircle($user, $verification)) {
            return true;
        }

        return Verification::visibleTo($user)->whereKey($verification->id)->exists();
    }

    protected function sameCircle(User $user, Verification $verification): bool
    {
        $complaintCircle = $verification->complaint?->circle_id
            ?? $verification->officer?->circle_id;

        // If complaint has no circle yet, fall back to officer circle / allow CI with circle
        if (!$complaintCircle) {
            return (bool) $user->circle_id;
        }

        if (!$user->circle_id) {
            return false;
        }

        return (int) $complaintCircle === (int) $user->circle_id;
    }

    public function submit(User $user, Verification $verification): bool
    {
        if ($user->hasAnyRole(['admin', 'circle_incharge'])) {
            return true;
        }

        return (int) $user->id === (int) $verification->verification_officer_id;
    }
}
