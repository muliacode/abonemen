<?php

namespace App\Enums;

/**
 * The default roles every Household starts with.
 */
enum HouseholdRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Editor => __('Editor'),
            self::Viewer => __('Viewer'),
        };
    }

    /**
     * Get the stored permission names this role carries.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => array_map(
                fn (HouseholdPermission $permission) => $permission->named($permission->isScoped() ? PermissionScope::All : null),
                HouseholdPermission::cases(),
            ),
            self::Editor => [
                HouseholdPermission::ReadData->named(PermissionScope::All),
                HouseholdPermission::WriteData->named(PermissionScope::All),
            ],
            self::Viewer => [
                HouseholdPermission::ReadData->named(PermissionScope::Visible),
            ],
        };
    }
}
