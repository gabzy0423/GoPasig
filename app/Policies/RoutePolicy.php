<?php

namespace App\Policies;

use App\Models\Route;
use App\Models\User;

class RoutePolicy
{
    public function view(User $user, Route $route): bool
    {
        return $user->role === 'admin' || $user->role === 'fleet_manager';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, Route $route): bool
    {
        return $user->role === 'admin';
    }

    public function delete(User $user, Route $route): bool
    {
        return $user->role === 'admin';
    }

    public function restore(User $user, Route $route): bool
    {
        return $user->role === 'admin';
    }

    public function forceDelete(User $user, Route $route): bool
    {
        return $user->role === 'admin';
    }
}
