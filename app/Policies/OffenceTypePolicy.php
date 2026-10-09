<?php

namespace App\Policies;

use App\Models\OffenceType;
use App\Models\User;

class OffenceTypePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, OffenceType $offenceType): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, OffenceType $offenceType): bool
    {
        return $user->hasRole('admin');
    }
}
