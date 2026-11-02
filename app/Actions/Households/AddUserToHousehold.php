<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddUserToHousehold
{
    /**
     * Make the user part of the household through a new member carrying their name, with the given role.
     */
    public function handle(Household $household, User $user, HouseholdRole $role): Member
    {
        return DB::transaction(function () use ($household, $user, $role) {
            $member = $household->members()->create([
                'name' => $user->name,
                'user_id' => $user->id,
            ]);

            $user->assignHouseholdRole($household, $role);

            return $member;
        });
    }
}
