<?php

namespace App\Policies;

use App\Models\MaintenanceRecord;
use App\Models\User;

class MaintenanceRecordPolicy
{
    public function view(User $user, MaintenanceRecord $record): bool
    {
        return $user->role === 'admin' || $user->role === 'fleet_manager';
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin' || $user->role === 'fleet_manager';
    }

    public function update(User $user, MaintenanceRecord $record): bool
    {
        return $user->role === 'admin' || $user->role === 'fleet_manager';
    }

    public function delete(User $user, MaintenanceRecord $record): bool
    {
        return $user->role === 'admin';
    }

    public function restore(User $user, MaintenanceRecord $record): bool
    {
        return $user->role === 'admin';
    }

    public function forceDelete(User $user, MaintenanceRecord $record): bool
    {
        return $user->role === 'admin';
    }
}
