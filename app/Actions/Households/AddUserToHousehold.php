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
     * Make the user part of the household through a member, with the given role.
     *
     * Without a member, a new one carrying the user's name is created.
     */
    public function handle(Household $household, User $user, HouseholdRole $role, ?Member $member = null): Member
    {
        return DB::transaction(function () use ($household, $user, $role, $member) {
            $member ??= $household->members()->create(['name' => $user->name]);

            $member->update(['user_id' => $user->id]);

            $user->assignHouseholdRole($household, $role);

            return $member;
        });
    }
}
