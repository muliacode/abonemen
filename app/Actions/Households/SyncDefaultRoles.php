<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncDefaultRoles
{
    /**
     * Create the default household roles, shared by every household, with their permissions.
     */
    public function handle(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId(null);

        foreach (HouseholdRole::cases() as $role) {
            $permissions = array_map(
                fn (string $name) => Permission::findOrCreate($name, 'web'),
                $role->permissions(),
            );

            Role::firstOrCreate([
                'name' => $role->value,
                'guard_name' => 'web',
                config('permission.column_names.team_foreign_key') => null,
            ])->syncPermissions($permissions);
        }

        $registrar->setPermissionsTeamId($previous);
        $registrar->forgetCachedPermissions();
    }
}
