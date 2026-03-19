<?php

namespace App\Enums;

/**
 * Maps system role names to numeric hierarchy levels.
 *
 * Higher levels can assign/manage lower levels but not equal or higher.
 * Owner (is_owner flag) is the highest at 100.
 */
enum RoleLevel: int
{
    case Owner = 100;
    case Admin = 80;
    case OperationsManager = 60;
    case Sales = 40;
    case Warehouse = 35;
    case ReadOnly = 20;

    /**
     * Resolve a role name to its hierarchy level.
     *
     * Returns null for custom (non-system) roles, which default to a
     * low level to prevent privilege escalation.
     */
    public static function fromRoleName(string $name): ?self
    {
        return match ($name) {
            'Admin' => self::Admin,
            'Operations Manager' => self::OperationsManager,
            'Sales' => self::Sales,
            'Warehouse' => self::Warehouse,
            'Read Only' => self::ReadOnly,
            default => null,
        };
    }

    /**
     * Get the numeric level for a role name.
     *
     * Custom roles default to level 0 (can only be assigned by anyone
     * with any system role).
     */
    public static function levelFor(string $roleName): int
    {
        $level = self::fromRoleName($roleName);

        return $level !== null ? $level->value : 0;
    }

    /**
     * Get the level for a user based on their highest role.
     *
     * Owner flag grants the highest level regardless of assigned roles.
     */
    public static function forUser(\App\Models\User $user): int
    {
        if ($user->isOwner()) {
            return self::Owner->value;
        }

        $roleNames = $user->getRoleNames();

        if ($roleNames->isEmpty()) {
            return 0;
        }

        return $roleNames->map(fn (string $name): int => self::levelFor($name))->max();
    }
}
