<?php

namespace App\Actions\Households;

use App\Exceptions\MemberException;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

class RemoveMember
{
    /**
     * Remove a Member from its Household.
     *
     * When the Member has a User, that User leaves the Household: their role here goes with it. Their account and
     * their other Households are untouched.
     *
     * @throws MemberException when the Member holds Subscriptions, is the last Owner, or is the only Member
     */
    public function handle(Member $member): void
    {
        DB::transaction(function () use ($member) {
            $household = $member->household;
            $household->lockMembers();

            $user = $member->user;

            if ($member->holdsSubscriptions()) {
                throw MemberException::holdsSubscriptions();
            }

            if ($household->isOnlyOwner($member)) {
                throw MemberException::lastOwner();
            }

            if ($household->members()->count() <= 1) {
                throw MemberException::lastMember();
            }

            $user?->forgetHouseholdRoles($household);
            $member->delete();

            if ($user?->isCurrentHousehold($household)) {
                $user->update(['current_household_id' => $user->fallbackHousehold()?->id]);
            }
        });
    }
}
