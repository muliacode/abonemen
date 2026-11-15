<?php

namespace App\Actions\Households;

use App\Exceptions\MemberException;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The one place a Member is created or changed, so that the label a Member is shown by stays unique within its
 * Household.
 *
 * Names are stored protected, so the database cannot enforce this: the Household row is locked and the labels are
 * compared in memory. Anything that makes or renames a Member goes through here.
 */
class SaveMember
{
    /**
     * Add a Member to the Household, linked to the User if one is given.
     *
     * @throws MemberException when another Member already goes by the label
     */
    public function create(Household $household, string $name, ?string $nickname = null, ?User $user = null): Member
    {
        return DB::transaction(function () use ($household, $name, $nickname, $user) {
            [$name, $nickname] = $this->clean($name, $nickname);

            $household->lockMembers();
            $this->ensureLabelFree($household, $nickname ?? $name);

            return $household->members()->create([
                'name' => $name,
                'nickname' => $nickname,
                'user_id' => $user?->id,
            ]);
        });
    }

    /**
     * Change a Member's name and nickname.
     *
     * @throws MemberException when another Member already goes by the new label
     */
    public function update(Member $member, string $name, ?string $nickname): Member
    {
        return DB::transaction(function () use ($member, $name, $nickname) {
            [$name, $nickname] = $this->clean($name, $nickname);

            $member->household->lockMembers();
            $this->ensureLabelFree($member->household, $nickname ?? $name, $member);

            $member->update(['name' => $name, 'nickname' => $nickname]);

            return $member;
        });
    }

    /**
     * Trim the name and nickname, and treat a blank nickname as none.
     *
     * @return array{0: string, 1: string|null}
     */
    private function clean(string $name, ?string $nickname): array
    {
        $nickname = $nickname === null ? '' : trim($nickname);

        return [trim($name), $nickname === '' ? null : $nickname];
    }

    /**
     * @throws MemberException when a Member other than the given one already goes by the label
     */
    private function ensureLabelFree(Household $household, string $label, ?Member $except = null): void
    {
        $taken = Member::where('household_id', $household->getKey())
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get()
            ->first(fn (Member $other) => Member::sameLabel($other->label(), $label));

        if ($taken) {
            throw MemberException::labelTaken($taken->label());
        }
    }
}
