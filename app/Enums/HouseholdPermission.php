<?php

namespace App\Enums;

/**
 * An ability within a Household. Scoped abilities are stored as "ability:scope",
 * for example "data.write:own".
 */
enum HouseholdPermission: string
{
    case ManageHousehold = 'household.manage';
    case ReadData = 'data.read';
    case WriteData = 'data.write';

    /**
     * Get the stored permission name, with its scope if it has one.
     */
    public function named(?PermissionScope $scope = null): string
    {
        return $scope ? "{$this->value}:{$scope->value}" : $this->value;
    }
}
