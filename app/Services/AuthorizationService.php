<?php

namespace App\Services;

use App\Models\User;

class AuthorizationService
{
    const ROLE_ADMIN = 'admin';
    const ROLE_FLEET_MANAGER = 'fleet_manager';

    /**
     * Check if user can delete a resource (admin only)
     */
    public static function canDelete(User $user): bool
    {
        return $user->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user can create a resource (admin only)
     */
    public static function canCreate(User $user): bool
    {
        return $user->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user can update a resource (admin only)
     */
    public static function canUpdate(User $user): bool
    {
        return $user->role === self::ROLE_ADMIN;
    }

    /**
     * Check if user can view resources (admin or fleet_manager)
     */
    public static function canView(User $user): bool
    {
        return in_array($user->role, [self::ROLE_ADMIN, self::ROLE_FLEET_MANAGER]);
    }

    /**
     * Check if user can manage schedules (admin or fleet_manager)
     */
    public static function canManageSchedules(User $user): bool
    {
        return in_array($user->role, [self::ROLE_ADMIN, self::ROLE_FLEET_MANAGER]);
    }

    /**
     * Check if user can manage maintenance (admin or fleet_manager)
     */
    public static function canManageMaintenance(User $user): bool
    {
        return in_array($user->role, [self::ROLE_ADMIN, self::ROLE_FLEET_MANAGER]);
    }

    /**
     * Check if user can suspend a driver (admin or fleet_manager)
     */
    public static function canSuspendDriver(User $user): bool
    {
        return in_array($user->role, [self::ROLE_ADMIN, self::ROLE_FLEET_MANAGER]);
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
