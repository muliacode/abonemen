<?php

namespace App\Policies;

use App\Enums\HouseholdPermission;
use App\Models\Household;
use App\Models\User;

class HouseholdPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Household $household): bool
    {
        return $user->belongsToHousehold($household);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can manage the household itself.
     */
    public function update(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::ManageHousehold);
    }

    /**
     * Determine whether the user can read the household's data, at any scope.
     */
    public function readData(User $user, Household $household): bool
    {
        return $user->householdPermissionScopes($household, HouseholdPermission::ReadData) !== [];
    }

    /**
     * Determine whether the user can change the household's data, at any scope.
     */
    public function writeData(User $user, Household $household): bool
    {
        return $user->householdPermissionScopes($household, HouseholdPermission::WriteData) !== [];
    }
}
