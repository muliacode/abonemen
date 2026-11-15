<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Exceptions\MemberException;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

class ChangeMemberRole
{
    /**
     * Give the User behind a Member a new role in the Member's Household, unless that would leave the Household
     * without an Owner who can sign in.
     *
     * @throws MemberException when the Member is not linked to a User, or is the last Owner and would stop being one
     */
    public function handle(Member $member, HouseholdRole $role): void
    {
        DB::transaction(function () use ($member, $role) {
            $household = $member->household;
            $household->lockMembers();

            $user = $member->user ?? throw MemberException::noUser();

            if ($role !== HouseholdRole::Owner && $household->isOnlyOwner($member)) {
                throw MemberException::lastOwner();
            }

            $user->assignHouseholdRole($household, $role);
        });
    }
}
