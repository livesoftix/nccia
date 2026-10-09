<?php

namespace App\Policies;

use App\Models\InvestigationOfficer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvestigationOfficerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'circle_incharge', 'director_general'])
            || $user->can('io_records');
    }

    public function view(User $user, InvestigationOfficer $investigationOfficer): bool
    {
        if ($user->hasAnyRole(['admin', 'director_general'])) {
            return true;
        }

        if ($user->hasRole('circle_incharge')) {
            return $this->sameCircle($user, $investigationOfficer);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'circle_incharge', 'director_general']);
    }

    public function update(User $user, InvestigationOfficer $investigationOfficer): bool
    {
        if ($user->hasAnyRole(['admin', 'director_general'])) {
            return true;
        }

        return $user->hasRole('circle_incharge') && $this->sameCircle($user, $investigationOfficer);
    }

    public function delete(User $user, InvestigationOfficer $investigationOfficer): bool
    {
        return $user->hasAnyRole(['admin', 'director_general']);
    }

    public function manageAccess(User $user, InvestigationOfficer $investigationOfficer): bool
    {
        if (!$this->update($user, $investigationOfficer)) {
            return false;
        }
        if ($user->hasAnyRole(['admin', 'director_general', 'DG']) || !$investigationOfficer->user_id) {
            return true;
        }
        $target = $investigationOfficer->user;
        $roles = $target?->roles()->pluck('name')->all() ?? [];
        return $target && $roles && !array_diff($roles, ['investigation_officer'])
            && (!$target->role || $target->role === 'investigation_officer')
            && (int) $user->circle_id === (int) $target->circle_id;
    }

    protected function sameCircle(User $user, InvestigationOfficer $officer): bool
    {
        if (!$user->circle_id) {
            return false;
        }

        $circle = $user->circle;
        if (!$circle) {
            return false;
        }

        return strcasecmp((string) $officer->circle, (string) $circle->name) === 0
            || strcasecmp((string) $officer->circle, (string) $circle->code) === 0;
    }
}
