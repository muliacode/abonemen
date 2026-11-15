<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Exceptions\MemberException;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AddUserToHousehold
{
    public function __construct(private SaveMember $saveMember)
    {
        //
    }

    /**
     * Make the user part of the household through a new member carrying their name, with the given role.
     *
     * Refused when another Member in the household already goes by the user's name.
     *
     * @throws MemberException when another Member already goes by the label
     */
    public function handle(Household $household, User $user, HouseholdRole $role): Member
    {
        return DB::transaction(function () use ($household, $user, $role) {
            $member = $this->saveMember->create($household, $user->name, null, $user);

            $user->assignHouseholdRole($household, $role);

            return $member;
        });
    }
}
