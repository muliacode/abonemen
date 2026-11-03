<?php

namespace Tests\Feature\Households;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HouseholdSwitchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_in_several_households_can_switch_between_them(): void
    {
        $user = User::factory()->create();
        $first = Household::factory()->withUser($user)->create(['name' => 'Home']);
        $second = Household::factory()->withUser($user, HouseholdRole::Viewer)->create(['name' => 'Parents']);

        $this->assertTrue($user->fresh()->isCurrentHousehold($first));

        $this->actingAs($user);

        Livewire::test('household-switcher')
            ->call('switchHousehold', $second->ulid)
            ->assertRedirect();

        $this->assertTrue($user->fresh()->isCurrentHousehold($second));
    }

    public function test_the_switcher_only_lists_the_households_the_user_belongs_to(): void
    {
        $user = User::factory()->create();
        Household::factory()->withUser($user)->create(['name' => 'Home']);
        Household::factory()->withUser($user, HouseholdRole::Viewer)->create(['name' => 'Parents']);
        Household::factory()->create(['name' => 'Strangers']);

        $this->actingAs($user);

        Livewire::test('household-switcher')
            ->assertSee('Home')
            ->assertSee('Parents')
            ->assertDontSee('Strangers');
    }

    public function test_a_user_cannot_switch_to_a_household_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $own = Household::factory()->withUser($user)->create();
        $other = Household::factory()->create();

        $this->actingAs($user);

        Livewire::test('household-switcher')
            ->call('switchHousehold', $other->ulid)
            ->assertForbidden();

        $this->assertTrue($user->fresh()->isCurrentHousehold($own));
    }

    public function test_visiting_a_households_dashboard_makes_it_the_current_household(): void
    {
        $user = User::factory()->create();
        $first = Household::factory()->withUser($user)->create();
        $second = Household::factory()->withUser($user)->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['current_household' => $second->ulid]))
            ->assertOk();

        $this->assertTrue($user->fresh()->isCurrentHousehold($second));
        $this->assertFalse($user->fresh()->isCurrentHousehold($first));
    }

    public function test_the_dashboard_of_another_household_is_forbidden(): void
    {
        $user = User::factory()->create();
        Household::factory()->withUser($user)->create();
        $other = Household::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['current_household' => $other->ulid]))
            ->assertForbidden();
    }

    public function test_the_current_household_is_shown_and_others_data_is_not(): void
    {
        $user = User::factory()->create();
        $home = Household::factory()->withUser($user)->create(['name' => 'Home']);
        Household::factory()->create(['name' => 'Strangers']);

        $this->actingAs($user)
            ->get(route('dashboard', ['current_household' => $home->ulid]))
            ->assertOk()
            ->assertSee('Home')
            ->assertDontSee('Strangers');
    }

    public function test_the_households_page_lists_only_the_users_households_with_their_role(): void
    {
        $user = User::factory()->create();
        Household::factory()->withUser($user, HouseholdRole::Owner)->create(['name' => 'Home']);
        Household::factory()->withUser($user, HouseholdRole::Viewer)->create(['name' => 'Parents']);
        Household::factory()->create(['name' => 'Strangers']);

        $this->actingAs($user)
            ->get(route('households.index'))
            ->assertOk()
            ->assertSeeInOrder(['Home', 'Owner', 'Parents', 'Viewer'])
            ->assertSee('data-test="household-edit-button"', false)
            ->assertSee('data-test="household-view-button"', false)
            ->assertDontSee('Strangers');
    }

    public function test_the_households_page_lists_households_alphabetically_although_names_are_stored_encrypted(): void
    {
        $user = User::factory()->create();

        // Created out of order, with mixed case: the stored ciphertext has no alphabetical order.
        $names = ['mango', 'Cherry', 'banana', 'Zebra', 'apple', 'Quince', 'Date', 'fig'];
        foreach ($names as $name) {
            Household::factory()->withUser($user)->create(['name' => $name]);
        }

        $this->actingAs($user)
            ->get(route('households.index'))
            ->assertOk()
            ->assertSeeInOrder(['apple', 'banana', 'Cherry', 'Date', 'fig', 'mango', 'Quince', 'Zebra']);
    }

    public function test_leaving_a_household_falls_back_to_the_alphabetically_first_other_household(): void
    {
        $user = User::factory()->create();
        foreach (['mango', 'Cherry', 'banana', 'Zebra', 'apple'] as $name) {
            Household::factory()->withUser($user)->create(['name' => $name]);
        }
        $current = Household::factory()->withUser($user)->create(['name' => 'Aardvark']);

        $this->assertSame('apple', $user->fallbackHousehold($current)->name);
    }

    public function test_a_user_without_a_household_can_open_the_households_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('households.index'))
            ->assertOk()
            ->assertSee('Create one to get started');
    }
}
