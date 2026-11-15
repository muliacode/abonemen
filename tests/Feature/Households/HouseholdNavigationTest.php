<?php

namespace Tests\Feature\Households;

use App\Actions\Households\AddUserToHousehold;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Managing the Household the User is working in is one step from anywhere, and its pages are one menu.
 */
class HouseholdNavigationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->household = Household::factory()->withUser($this->owner, HouseholdRole::Owner)->create(['name' => 'Home']);
    }

    private function joinAs(HouseholdRole $role): User
    {
        $user = User::factory()->create();
        $household = $this->household;
        app(AddUserToHousehold::class)->handle($household, $user, $role);
        $user->switchHousehold($household);

        return $user->fresh();
    }

    public function test_the_rail_leads_to_the_current_household_in_one_step(): void
    {
        $this->actingAs($this->owner)->get(route('dashboard', $this->household))
            ->assertOk()
            ->assertSee('data-test="household-link"', false)
            ->assertSee('href="'.route('households.edit', $this->household).'"', false);
    }

    public function test_every_member_sees_the_household_in_the_rail_whatever_their_role(): void
    {
        $viewer = $this->joinAs(HouseholdRole::Viewer);

        $this->actingAs($viewer)->get(route('dashboard', $this->household))
            ->assertSee('data-test="household-link"', false);
    }

    public function test_a_user_without_a_current_household_has_no_household_item(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('data-test="household-link"', false);
    }

    public function test_the_household_item_is_the_current_page_on_its_own_pages_and_settings_is_not(): void
    {
        $html = $this->actingAs($this->owner)->get(route('households.members', $this->household))->getContent();

        $this->assertMatchesRegularExpression('/<a[^>]*data-test="household-link"[^>]*aria-current="page"|<a[^>]*aria-current="page"[^>]*data-test="household-link"/', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"') - substr_count($html, 'data-test="household-members-link"'));
    }

    public function test_another_households_pages_belong_to_settings_not_to_the_rail_item(): void
    {
        $other = Household::factory()->withUser($this->owner, HouseholdRole::Owner)->create(['name' => 'Parents']);
        $this->owner->switchHousehold($this->household);

        $html = $this->actingAs($this->owner)->get(route('households.edit', $other))->getContent();

        $this->assertDoesNotMatchRegularExpression('/<a[^>]*data-test="household-link"[^>]*aria-current="page"/', $html);
    }

    public function test_an_owner_moves_between_details_members_and_connections_from_one_menu(): void
    {
        foreach (['households.edit', 'households.members', 'households.connections'] as $route) {
            $this->actingAs($this->owner)->get(route($route, $this->household))
                ->assertSee('data-test="household-nav"', false)
                ->assertSee('data-test="household-details-link"', false)
                ->assertSee('data-test="household-members-link"', false)
                ->assertSee('data-test="household-connections-link"', false);
        }
    }

    public function test_the_menu_marks_the_page_the_user_is_on(): void
    {
        $html = $this->actingAs($this->owner)->get(route('households.members', $this->household))->getContent();

        $this->assertMatchesRegularExpression('/<a[^>]*data-test="household-members-link"[^>]*aria-current="page"|<a[^>]*aria-current="page"[^>]*data-test="household-members-link"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*data-test="household-details-link"[^>]*aria-current/', $html);
    }

    public function test_a_user_who_cannot_manage_sees_details_and_members_but_not_connections(): void
    {
        $editor = $this->joinAs(HouseholdRole::Editor);

        $this->actingAs($editor)->get(route('households.edit', $this->household))
            ->assertSee('data-test="household-details-link"', false)
            ->assertSee('data-test="household-members-link"', false)
            ->assertDontSee('data-test="household-connections-link"', false);
    }

    public function test_the_household_pages_are_headed_by_the_households_name(): void
    {
        $this->actingAs($this->owner)->get(route('households.members', $this->household))
            ->assertSeeInOrder(['<h1', 'Home', '</h1>'], false);
    }
}
