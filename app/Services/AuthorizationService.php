<?php

namespace App\Services;

use App\Models\User;

class AuthorizationService
{
    /**
     * Check if user can delete a resource (admin only)
     */
    public static function canDelete(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Check if user can create a resource (admin only)
     */
    public static function canCreate(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Check if user can update a resource (admin only)
     */
    public static function canUpdate(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Check if user can view resources (admin or dispatcher)
     */
    public static function canView(User $user): bool
    {
        return in_array($user->role, ['admin', 'dispatcher']);
    }

    /**
     * Check if user can manage schedules (admin or dispatcher)
     */
    public static function canManageSchedules(User $user): bool
    {
        return in_array($user->role, ['admin', 'dispatcher']);
    }

    /**
     * Check if user can manage maintenance (admin or dispatcher)
     */
    public static function canManageMaintenance(User $user): bool
    {
        return in_array($user->role, ['admin', 'dispatcher']);
    }

    /**
     * Check if user can suspend a driver (admin or dispatcher)
     */
    public static function canSuspendDriver(User $user): bool
    {
        return in_array($user->role, ['admin', 'dispatcher']);
    }

    /**
     * Generate authorization error response
     */
    public static function deniedResponse(string $action = 'perform this action'): array
    {
        return [
            'success' => false,
            'message' => "Unauthorized: You do not have permission to {$action}."
        ];
    }
}
