<?php

namespace Tests\Feature\Households;

use App\Actions\Households\ChangeMemberRole;
use App\Actions\Households\RemoveMember;
use App\Actions\Households\SaveMember;
use App\Enums\HouseholdRole;
use App\Exceptions\MemberException;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * An Owner manages the Household's Members: who they are, who can sign in as one, and what role that User holds. A
 * Household always keeps at least one Member and at least one Owner linked to a User.
 */
class MemberRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->household = Household::factory()->withUser($this->owner, HouseholdRole::Owner)->create();
    }

    private function join(HouseholdRole $role, ?Household $household = null): Member
    {
        $household ??= $this->household;
        $user = User::factory()->create();

        $member = app(SaveMember::class)->create($household, fake()->unique()->name(), null, $user);
        $user->assignHouseholdRole($household, $role);

        return $member;
    }

    private function ownerMember(): Member
    {
        return $this->household->members()->where('user_id', $this->owner->id)->firstOrFail();
    }

    public function test_only_an_owner_manages_members(): void
    {
        $this->assertTrue(Gate::forUser($this->owner)->allows('manageMembers', $this->household));

        foreach ([HouseholdRole::Editor, HouseholdRole::Viewer] as $role) {
            $user = $this->join($role)->user;

            $this->assertFalse(Gate::forUser($user)->allows('manageMembers', $this->household), $role->value);
        }

        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('manageMembers', $this->household));
    }

    public function test_an_owner_can_make_another_user_an_owner_too(): void
    {
        $member = $this->join(HouseholdRole::Viewer);

        app(ChangeMemberRole::class)->handle($member, HouseholdRole::Owner);

        $this->assertSame(HouseholdRole::Owner, $member->user->householdRole($this->household));
        $this->assertSame(HouseholdRole::Owner, $this->owner->householdRole($this->household));
    }

    public function test_an_owner_can_step_down_while_another_owner_remains(): void
    {
        $this->join(HouseholdRole::Owner);

        app(ChangeMemberRole::class)->handle($this->ownerMember(), HouseholdRole::Editor);

        $this->assertSame(HouseholdRole::Editor, $this->owner->householdRole($this->household));
    }

    public function test_the_last_owner_cannot_be_demoted(): void
    {
        $this->join(HouseholdRole::Editor);

        try {
            app(ChangeMemberRole::class)->handle($this->ownerMember(), HouseholdRole::Viewer);
            $this->fail('The last Owner should not be demotable.');
        } catch (MemberException $exception) {
            $this->assertSame(MemberException::LAST_OWNER, $exception->reason);
            $this->assertSame(HouseholdRole::Owner, $this->owner->householdRole($this->household));
        }
    }

    public function test_keeping_the_last_owner_an_owner_is_not_a_change_and_is_allowed(): void
    {
        app(ChangeMemberRole::class)->handle($this->ownerMember(), HouseholdRole::Owner);

        $this->assertSame(HouseholdRole::Owner, $this->owner->householdRole($this->household));
    }

    public function test_a_member_not_linked_to_a_user_has_no_role_to_change(): void
    {
        $child = app(SaveMember::class)->create($this->household, 'Robin');

        try {
            app(ChangeMemberRole::class)->handle($child, HouseholdRole::Viewer);
            $this->fail('A Member that is not linked to a User has no role.');
        } catch (MemberException $exception) {
            $this->assertSame(MemberException::NO_USER, $exception->reason);
        }
    }

    public function test_a_member_not_linked_to_a_user_can_be_removed(): void
    {
        $child = app(SaveMember::class)->create($this->household, 'Robin');

        app(RemoveMember::class)->handle($child);

        $this->assertModelMissing($child);
        $this->assertSame(1, $this->household->members()->count());
    }

    public function test_removing_a_member_linked_to_a_user_removes_the_user_but_not_their_account_or_other_households(): void
    {
        $member = $this->join(HouseholdRole::Editor);
        $user = $member->user;
        $other = Household::factory()->withUser($user, HouseholdRole::Owner)->create();
        $user->switchHousehold($this->household);

        app(RemoveMember::class)->handle($member);

        $user = $user->fresh();
        $this->assertModelExists($user);
        $this->assertFalse($user->belongsToHousehold($this->household));
        $this->assertTrue($user->belongsToHousehold($other));
        $this->assertSame(HouseholdRole::Owner, $user->householdRole($other));
        $this->assertSame($other->id, $user->current_household_id);
    }

    public function test_a_removed_user_keeps_no_role_that_a_later_join_could_inherit(): void
    {
        $member = $this->join(HouseholdRole::Editor);
        $user = $member->user;

        app(RemoveMember::class)->handle($member);

        $this->assertSame(0, DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where(config('permission.column_names.team_foreign_key'), $this->household->id)
            ->count());
    }

    public function test_a_user_removed_from_their_only_household_is_left_without_a_current_one(): void
    {
        $member = $this->join(HouseholdRole::Viewer);
        $member->user->switchHousehold($this->household);

        app(RemoveMember::class)->handle($member);

        $this->assertNull($member->user->fresh()->current_household_id);
    }

    public function test_an_owner_can_remove_another_owner_and_even_themselves_while_one_remains(): void
    {
        $second = $this->join(HouseholdRole::Owner);

        app(RemoveMember::class)->handle($this->ownerMember());

        $this->assertFalse($this->owner->fresh()->belongsToHousehold($this->household));
        $this->assertSame(HouseholdRole::Owner, $second->user->householdRole($this->household));
    }

    public function test_the_last_owner_cannot_be_removed(): void
    {
        $this->join(HouseholdRole::Editor);

        try {
            app(RemoveMember::class)->handle($this->ownerMember());
            $this->fail('The last Owner should not be removable.');
        } catch (MemberException $exception) {
            $this->assertSame(MemberException::LAST_OWNER, $exception->reason);
            $this->assertTrue($this->owner->belongsToHousehold($this->household));
        }
    }

    public function test_the_only_member_cannot_be_removed(): void
    {
        $household = Household::factory()->create();
        $only = app(SaveMember::class)->create($household, 'Robin');

        try {
            app(RemoveMember::class)->handle($only);
            $this->fail('A Household keeps at least one Member.');
        } catch (MemberException $exception) {
            $this->assertSame(MemberException::LAST_MEMBER, $exception->reason);
            $this->assertModelExists($only);
        }
    }

    public function test_a_member_that_holds_subscriptions_cannot_be_removed(): void
    {
        // Subscriptions arrive with their own ticket (#13), which implements Member::holdsSubscriptions() for real
        // and replaces this stand-in with a Member that really holds one.
        $holder = new class extends Member
        {
            protected $table = 'members';

            public function holdsSubscriptions(): bool
            {
                return true;
            }
        };
        $child = app(SaveMember::class)->create($this->household, 'Robin');
        $holder = $holder->newQuery()->findOrFail($child->id);

        try {
            app(RemoveMember::class)->handle($holder);
            $this->fail('A Member must hand over its Subscriptions first.');
        } catch (MemberException $exception) {
            $this->assertSame(MemberException::HOLDS_SUBSCRIPTIONS, $exception->reason);
            $this->assertModelExists($child);
        }
    }

    public function test_no_member_holds_subscriptions_until_subscriptions_exist(): void
    {
        $this->assertFalse($this->ownerMember()->holdsSubscriptions());
    }
}
