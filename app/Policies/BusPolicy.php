<?php

namespace App\Policies;

use App\Models\Bus;
use App\Models\User;

class BusPolicy
{
    public function view(User $user, Bus $bus): bool
    {
        return $user->role === 'admin' || $user->role === 'fleet_manager';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Bus $bus): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, Bus $bus): bool
    {
        return $user->role === 'admin';
    }

    public function restore(User $user, Bus $bus): bool
    {
        return $user->role === 'admin';
    }

    public function forceDelete(User $user, Bus $bus): bool
    {
        return $user->role === 'admin';
    }
}
