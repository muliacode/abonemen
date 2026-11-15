<?php

namespace Tests\Feature\Households;

use App\Actions\Households\SaveMember;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Everyone in a Household sees its Members; only an Owner manages them.
 */
class MembersPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Olive Owner']);
        $this->household = Household::factory()->withUser($this->owner, HouseholdRole::Owner)->create();
    }

    private function member(string $name, ?string $nickname = null, ?HouseholdRole $role = null, ?User $user = null): Member
    {
        if ($role) {
            $user ??= User::factory()->create();
        }

        $member = app(SaveMember::class)->create($this->household, $name, $nickname, $user);
        $role && $user->assignHouseholdRole($this->household, $role);

        return $member;
    }

    private function page(?User $as = null): Testable
    {
        return Livewire::actingAs($as ?? $this->owner)->test('pages::households.members', ['household' => $this->household]);
    }

    private function addForm(?User $as = null): Testable
    {
        return Livewire::actingAs($as ?? $this->owner)->test('add-member-form', ['household' => $this->household]);
    }

    private function ownerMember(): Member
    {
        return $this->household->members()->where('user_id', $this->owner->id)->firstOrFail();
    }

    public function test_everyone_in_the_household_opens_the_page_but_a_stranger_does_not(): void
    {
        foreach ([HouseholdRole::Owner, HouseholdRole::Editor, HouseholdRole::Viewer] as $role) {
            $user = $role === HouseholdRole::Owner ? $this->owner : $this->member('Someone '.$role->value, null, $role)->user;

            $this->actingAs($user)->get(route('households.members', $this->household))->assertOk();
        }

        $this->actingAs(User::factory()->create())->get(route('households.members', $this->household))->assertForbidden();
    }

    public function test_the_household_settings_link_to_the_members_for_everyone(): void
    {
        $viewer = $this->member('Vera', null, HouseholdRole::Viewer)->user;

        $this->actingAs($viewer)->get(route('households.edit', $this->household))
            ->assertSee(route('households.members', $this->household), false);
    }

    public function test_members_are_listed_by_label_with_who_can_sign_in_and_their_role(): void
    {
        $this->member('Robin');
        $this->member('alex', 'Zed');
        $this->member('Sam', null, HouseholdRole::Viewer);

        $this->page()
            ->assertSeeInOrder(['Olive Owner', 'Robin', 'Sam', 'Zed'])
            ->assertSee('Viewer')
            ->assertSee('Owner');
    }

    public function test_a_member_with_a_nickname_is_headed_by_it_and_keeps_its_real_name_beneath(): void
    {
        $this->member('Alex', 'Alex K');

        $html = $this->page()->html();

        $this->assertMatchesRegularExpression('/data-test="member-label"[^>]*>\s*Alex K\s*</', $html);
        $this->assertMatchesRegularExpression('/data-test="member-real-name"[^>]*>\s*Alex\s*</', $html);
    }

    public function test_the_list_marks_who_can_sign_in_and_shows_nothing_private_about_them(): void
    {
        $user = User::factory()->create(['name' => 'Platform Pat', 'email' => 'pat@private.example']);
        $this->member('Pat', null, HouseholdRole::Editor, $user);
        $this->member('Robin');

        $html = $this->page()->html();

        $this->assertSame(2, substr_count($html, 'data-test="member-login"'));
        $this->assertStringNotContainsString('pat@private.example', $html);
        $this->assertStringNotContainsString('Platform Pat', $html);
    }

    public function test_a_viewer_sees_the_list_without_any_controls(): void
    {
        $viewer = $this->member('Vera', null, HouseholdRole::Viewer)->user;
        $this->member('Robin');

        $html = $this->page($viewer)->assertSee('Robin')->html();

        $this->assertStringNotContainsString('data-test="edit-member-button"', $html);
        $this->assertStringNotContainsString('data-test="remove-member-button"', $html);
        $this->assertStringNotContainsString('add-member-form', $html);
    }

    public function test_an_owner_sees_the_controls(): void
    {
        $this->member('Robin');

        $html = $this->page()->html();

        $this->assertStringContainsString('data-test="edit-member-button"', $html);
        $this->assertStringContainsString('data-test="remove-member-button"', $html);
    }

    // Adding

    public function test_an_owner_adds_a_member_without_a_login(): void
    {
        $this->addForm()
            ->set('name', 'Robin')
            ->call('add')
            ->assertHasNoErrors()
            ->assertSet('name', '')
            ->assertSet('status', 'Member added.');

        $robin = $this->household->members()->get()->firstWhere('name', 'Robin');
        $this->assertNotNull($robin);
        $this->assertNull($robin->user_id);
    }

    public function test_adding_a_member_refreshes_the_list(): void
    {
        $this->addForm()->set('name', 'Robin')->call('add')->assertDispatched('member-saved');
    }

    public function test_a_name_is_required_to_add_a_member(): void
    {
        $this->addForm()->set('name', '  ')->call('add')->assertHasErrors(['name' => 'required']);

        $this->assertSame(1, $this->household->members()->count());
    }

    public function test_a_repeated_name_asks_for_a_nickname_on_the_nickname_field(): void
    {
        $this->member('Alex');

        $this->addForm()->set('name', 'alex')->call('add')
            ->assertHasErrors('nickname')
            ->assertSee('Another Member here is already called Alex. Give this Member a nickname to tell them apart.');

        $this->assertSame(2, $this->household->members()->count());
    }

    public function test_a_repeated_name_is_added_with_a_nickname(): void
    {
        $this->member('Alex');

        $this->addForm()->set('name', 'Alex')->set('nickname', 'Alex K')->call('add')->assertHasNoErrors();

        $this->assertSame(3, $this->household->members()->count());
    }

    public function test_a_nickname_that_is_taken_asks_for_a_different_one(): void
    {
        $this->member('Alex');

        $this->addForm()->set('name', 'Sam')->set('nickname', 'Alex')->call('add')
            ->assertHasErrors('nickname')
            ->assertSee('Another Member here is already called Alex. Choose a different nickname.');
    }

    public function test_only_an_owner_can_add_a_member(): void
    {
        $editor = $this->member('Edie', null, HouseholdRole::Editor)->user;

        $this->addForm($editor)->set('name', 'Robin')->call('add')->assertForbidden();

        $this->assertNull($this->household->members()->get()->firstWhere('name', 'Robin'));
    }

    // Editing

    public function test_an_owner_renames_a_member_and_gives_it_a_nickname(): void
    {
        $robin = $this->member('Robin');

        $this->page()
            ->call('edit', $robin->id)
            ->assertSet('editName', 'Robin')
            ->set('editName', 'Robin Smith')
            ->set('editNickname', 'Rob')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $this->assertSame('Robin Smith', $robin->fresh()->name);
        $this->assertSame('Rob', $robin->fresh()->nickname);
    }

    public function test_a_blank_name_is_refused_when_editing(): void
    {
        $robin = $this->member('Robin');

        $this->page()->call('edit', $robin->id)->set('editName', '')->call('saveEdit')->assertHasErrors(['editName' => 'required']);

        $this->assertSame('Robin', $robin->fresh()->name);
    }

    public function test_renaming_onto_another_label_is_refused_on_the_nickname_field(): void
    {
        $this->member('Alex');
        $sam = $this->member('Sam');

        $this->page()->call('edit', $sam->id)->set('editName', 'Alex')->call('saveEdit')->assertHasErrors('editNickname');

        $this->assertSame('Sam', $sam->fresh()->name);
    }

    public function test_an_owner_changes_the_role_of_a_member_with_a_login(): void
    {
        $sam = $this->member('Sam', null, HouseholdRole::Viewer);

        $this->page()->call('edit', $sam->id)->assertSet('editRole', 'viewer')->set('editRole', 'owner')->call('saveEdit')->assertHasNoErrors();

        $this->assertSame(HouseholdRole::Owner, $sam->user->householdRole($this->household));
    }

    public function test_a_role_cannot_be_set_on_a_member_without_a_login(): void
    {
        $robin = $this->member('Robin');

        $this->page()->call('edit', $robin->id)->set('editRole', 'owner')->call('saveEdit');

        $this->assertNull($robin->fresh()->user_id);
        $this->assertSame(1, $this->household->members()->whereNotNull('user_id')->count());
    }

    public function test_a_role_must_be_one_of_the_default_roles(): void
    {
        $sam = $this->member('Sam', null, HouseholdRole::Viewer);

        $this->page()->call('edit', $sam->id)->set('editRole', 'admin')->call('saveEdit')->assertHasErrors('editRole');

        $this->assertSame(HouseholdRole::Viewer, $sam->user->householdRole($this->household));
    }

    public function test_the_last_owner_cannot_step_down_and_is_told_why(): void
    {
        $html = $this->page()->call('edit', $this->ownerMember()->id)->html();
        $this->assertStringContainsString('data-test="role-locked-note"', $html);

        $this->page()->call('edit', $this->ownerMember()->id)->set('editRole', 'viewer')->call('saveEdit')->assertHasErrors('editRole');

        $this->assertSame(HouseholdRole::Owner, $this->owner->householdRole($this->household));
    }

    public function test_an_owner_who_steps_down_is_sent_to_their_households(): void
    {
        $this->member('Second', null, HouseholdRole::Owner);

        $this->page()->call('edit', $this->ownerMember()->id)->set('editRole', 'editor')->call('saveEdit')
            ->assertRedirect(route('households.index'));

        $this->assertSame(HouseholdRole::Editor, $this->owner->householdRole($this->household));
    }

    public function test_only_an_owner_can_edit_a_member(): void
    {
        $editor = $this->member('Edie', null, HouseholdRole::Editor)->user;
        $robin = $this->member('Robin');

        $this->page($editor)->call('edit', $robin->id)->assertForbidden();
        $this->page($editor)->call('saveEdit')->assertForbidden();

        $this->assertSame('Robin', $robin->fresh()->name);
    }

    public function test_a_member_of_another_household_cannot_be_reached(): void
    {
        $stranger = Member::factory()->create();

        $this->page()->call('edit', $stranger->id)->assertNotFound();
        $this->page()->call('confirmRemove', $stranger->id)->assertNotFound();

        $this->assertModelExists($stranger);
    }

    // Removing

    public function test_an_owner_removes_a_member_after_confirming(): void
    {
        $robin = $this->member('Robin');

        $this->page()
            ->call('confirmRemove', $robin->id)
            ->assertSee('Remove Robin')
            ->call('remove')
            ->assertHasNoErrors()
            ->assertSet('status', 'Robin was removed.');

        $this->assertModelMissing($robin);
    }

    public function test_removing_a_member_with_a_login_says_they_lose_access_but_keep_their_account(): void
    {
        $sam = $this->member('Sam', null, HouseholdRole::Editor);

        $this->page()->call('confirmRemove', $sam->id)
            ->assertSee('Sam will lose access to this Household, and keeps their account.');
    }

    public function test_removing_a_member_removes_their_user_from_the_household_only(): void
    {
        $sam = $this->member('Sam', null, HouseholdRole::Editor);

        $this->page()->call('confirmRemove', $sam->id)->call('remove');

        $this->assertFalse($sam->user->fresh()->belongsToHousehold($this->household));
        $this->assertModelExists($sam->user);
    }

    public function test_the_last_owner_cannot_be_removed_and_the_button_says_why(): void
    {
        $html = $this->page()->html();
        preg_match('/<button[^>]*data-test="remove-member-button"[^>]*>/', $html, $button);
        $this->assertStringContainsString('disabled', $button[0]);
        $this->assertStringContainsString('data-test="last-owner-note"', $html);

        $this->page()->call('confirmRemove', $this->ownerMember()->id)->call('remove')->assertHasErrors('remove');

        $this->assertTrue($this->owner->belongsToHousehold($this->household));
    }

    public function test_an_owner_who_removes_themselves_is_sent_to_their_households(): void
    {
        $this->member('Second', null, HouseholdRole::Owner);

        $this->page()->call('confirmRemove', $this->ownerMember()->id)->call('remove')->assertRedirect(route('households.index'));

        $this->assertFalse($this->owner->belongsToHousehold($this->household));
    }

    public function test_only_an_owner_can_remove_a_member(): void
    {
        $editor = $this->member('Edie', null, HouseholdRole::Editor)->user;
        $robin = $this->member('Robin');

        $this->page($editor)->call('confirmRemove', $robin->id)->assertForbidden();
        $this->page($editor)->call('remove')->assertForbidden();

        $this->assertModelExists($robin);
    }
}
