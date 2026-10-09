<?php

namespace App\Policies;

use App\Models\Enquiry;
use App\Models\User;

class EnquiryPolicy
{
    /**
     * Roles that participate in the Enquiry stage (flowchart page 5).
     * Verification officers are intentionally excluded.
     */
    private function canAccessEnquiryModule(User $user): bool
    {
        $hasRole = function($role) use ($user) {
            return $user->hasRole($role) || ($user->role ?? null) === $role;
        };

        if ($hasRole('verification_officer')
            && !$user->hasAnyRole([
                'admin', 'circle_incharge', 'enquiry_officer', 'operator',
                'moharrar', 'reader_branch', 'ad_legal', 'dd_legal',
                'additional_director', 'director_general',
            ]) && !in_array($user->role ?? '', ['admin', 'circle_incharge', 'enquiry_officer', 'operator', 'moharrar', 'reader_branch'])) {
            return false;
        }

        return $user->hasAnyRole([
            'admin', 'circle_incharge', 'enquiry_officer', 'operator',
            'moharrar', 'reader_branch', 'ad_legal', 'dd_legal',
            'additional_director', 'director_general', 'investigation_officer',
        ]) || in_array($user->role ?? '', ['admin', 'circle_incharge', 'enquiry_officer', 'operator', 'moharrar', 'reader_branch', 'investigation_officer', 'inspector', 'sub_inspector', 'officer'])
        || $user->can('enquiries');
    }

    /** Who may register / create a new enquiry (matches enquiries UI access). */
    private function canCreateEnquiry(User $user): bool
    {
        return $user->hasAnyRole([
            'admin',
            'circle_incharge',
            'operator',
            'reader_branch',
            'moharrar',
            'enquiry_officer',
            'director_general',
            'ad_legal',
            'dd_legal',
            'additional_director',
        ]) || in_array($user->role ?? '', ['admin', 'circle_incharge', 'enquiry_officer', 'operator', 'moharrar', 'reader_branch', 'inspector'])
        || $user->can('enquiries');
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Enquiry $enquiry): bool
    {
        if ($user->seesAllData()) {
            return true;
        }

        return Enquiry::visibleTo($user)->whereKey($enquiry->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->canCreateEnquiry($user);
    }

    public function update(User $user, Enquiry $enquiry): bool
    {
        if (!$this->view($user, $enquiry)) {
            return false;
        }

        // Requirement #2 — once forwarded past the enquiry stage (CFR submitted,
        // legal review, disposed...), the enquiry officer keeps read access only.
        // Admin / DG retain an audited correction path (isLockedFor returns false
        // for them); circle incharge and legal roles are reviewers, not locked.
        if ($enquiry->isLockedFor($user)) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Enquiry $enquiry): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Register enquiry as DAC case/FIR (Circle Incharge, legal chain, Moharrar, Admin).
     */
    public function registerCase(User $user, Enquiry $enquiry): bool
    {
        if (!$this->canAccessEnquiryModule($user)) {
            return false;
        }

        if (!$user->hasAnyRole([
            'admin', 'circle_incharge', 'moharrar',
            'ad_legal', 'dd_legal', 'additional_director', 'director_general',
        ])) {
            return false;
        }

        if ($enquiry->hasRegisteredCase()) {
            return false;
        }

        return in_array($enquiry->status, [
            'cfr_submitted', 'approved', 'referred_court', 'in_progress', 'working', 'complete',
            'legal_review_dd', 'legal_review_ad', 'legal_review_dg',
        ], true);
    }
}
