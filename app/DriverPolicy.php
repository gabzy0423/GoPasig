<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;

class DriverPolicy
{
    public function view(User $user, Driver $driver): bool
    {
        return $user->role === 'admin' || $user->role === 'dispatcher';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $user->role === 'admin';
    }

    public function suspend(User $user, Driver $driver): bool
    {
        return $user->role === 'admin' || $user->role === 'dispatcher';
    }

    public function restore(User $user, Driver $driver): bool
    {
        return $user->role === 'admin';
    }

    public function forceDelete(User $user, Driver $driver): bool
    {
        return $user->role === 'admin';
    }
}
