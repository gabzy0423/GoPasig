<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    public function view(User $user, Schedule $schedule): bool
    {
        return $user->role === 'admin' || $user->role === 'dispatcher';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'dispatcher';
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $user->role === 'admin' || $user->role === 'dispatcher';
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return $user->role === 'admin';
    }

    public function restore(User $user, Schedule $schedule): bool
    {
        return $user->role === 'admin';
    }

    public function forceDelete(User $user, Schedule $schedule): bool
    {
        return $user->role === 'admin';
    }
}
