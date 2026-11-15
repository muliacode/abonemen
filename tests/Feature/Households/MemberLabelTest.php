<?php

namespace Tests\Feature\Households;

use App\Actions\Households\AddUserToHousehold;
use App\Actions\Households\SaveMember;
use App\Enums\HouseholdRole;
use App\Exceptions\MemberException;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Member is shown by its nickname when it has one, otherwise by its name, and that label is unique within its
 * Household. It is not unique across Households.
 */
class MemberLabelTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
    }

    private function save(): SaveMember
    {
        return app(SaveMember::class);
    }

    public function test_a_member_is_shown_by_its_nickname_when_it_has_one(): void
    {
        $plain = $this->save()->create($this->household, 'Alex');
        $nicknamed = $this->save()->create($this->household, 'Alex', 'Alex K');

        $this->assertSame('Alex', $plain->label());
        $this->assertSame('Alex K', $nicknamed->label());
        $this->assertSame('Alex', $nicknamed->name);
    }

    public function test_a_blank_nickname_means_no_nickname(): void
    {
        $member = $this->save()->create($this->household, '  Sam ', '   ');

        $this->assertSame('Sam', $member->name);
        $this->assertNull($member->nickname);
    }

    public function test_a_member_without_a_login_is_created_in_the_household(): void
    {
        $member = $this->save()->create($this->household, 'Robin');

        $this->assertNull($member->user_id);
        $this->assertTrue($member->household->is($this->household));
    }

    public function test_two_members_cannot_share_a_label_whatever_the_case_or_spacing(): void
    {
        $this->save()->create($this->household, 'Alex');

        $this->expectException(MemberException::class);

        $this->save()->create($this->household, '  aLEX ');
    }

    public function test_a_member_with_the_same_name_needs_a_nickname_to_tell_them_apart(): void
    {
        $this->save()->create($this->household, 'Alex');

        $second = $this->save()->create($this->household, 'Alex', 'Alex K');

        $this->assertSame('Alex K', $second->label());
        $this->assertSame(2, $this->household->members()->count());
    }

    public function test_a_nickname_cannot_be_another_members_name(): void
    {
        $this->save()->create($this->household, 'Alex');

        $this->expectException(MemberException::class);

        $this->save()->create($this->household, 'Sam', 'alex');
    }

    public function test_a_name_cannot_be_another_members_nickname_when_it_would_be_the_label(): void
    {
        $this->save()->create($this->household, 'Alexander', 'Alex');

        $this->expectException(MemberException::class);

        $this->save()->create($this->household, 'Alex');
    }

    public function test_a_name_is_free_to_repeat_once_it_is_hidden_behind_a_nickname(): void
    {
        $this->save()->create($this->household, 'Alex', 'Alex K');

        $member = $this->save()->create($this->household, 'Alex');

        $this->assertSame('Alex', $member->label());
    }

    public function test_the_same_label_is_fine_in_another_household(): void
    {
        $this->save()->create($this->household, 'Alex');

        $member = $this->save()->create(Household::factory()->create(), 'Alex');

        $this->assertSame('Alex', $member->label());
    }

    public function test_a_member_may_keep_its_own_label_when_it_is_saved(): void
    {
        $member = $this->save()->create($this->household, 'Alex');

        $updated = $this->save()->update($member, 'Alex', null);

        $this->assertSame('Alex', $updated->label());
    }

    public function test_renaming_a_member_onto_another_label_is_refused(): void
    {
        $this->save()->create($this->household, 'Alex');
        $sam = $this->save()->create($this->household, 'Sam');

        try {
            $this->save()->update($sam, 'Alex', null);
            $this->fail('The rename should have been refused.');
        } catch (MemberException $exception) {
            $this->assertSame('Sam', $sam->fresh()->name);
        }
    }

    public function test_a_nickname_cannot_be_dropped_when_the_name_would_then_collide(): void
    {
        $this->save()->create($this->household, 'Alex');
        $second = $this->save()->create($this->household, 'Alex', 'Alex K');

        $this->expectException(MemberException::class);

        $this->save()->update($second, 'Alex', null);
    }

    public function test_a_refused_label_names_the_clashing_label_so_the_screen_can_say_so(): void
    {
        $this->save()->create($this->household, 'Alex');

        try {
            $this->save()->create($this->household, 'ALEX');
            $this->fail('The label should have been refused.');
        } catch (MemberException $exception) {
            $this->assertSame(MemberException::LABEL_TAKEN, $exception->reason);
            $this->assertSame('Alex', $exception->label);
        }
    }

    public function test_adding_a_user_to_a_household_follows_the_same_rule(): void
    {
        $this->save()->create($this->household, 'Alex');
        $user = User::factory()->create(['name' => 'alex']);

        $this->expectException(MemberException::class);

        app(AddUserToHousehold::class)->handle($this->household, $user, HouseholdRole::Viewer);
    }

    public function test_a_user_who_joins_may_bring_a_nickname_to_tell_them_apart(): void
    {
        $this->save()->create($this->household, 'Alex');
        $user = User::factory()->create(['name' => 'Alex']);

        $member = app(AddUserToHousehold::class)->handle($this->household, $user, HouseholdRole::Viewer, 'Alex J');

        $this->assertSame('Alex J', $member->label());
        $this->assertSame($user->id, $member->user_id);
    }
}
