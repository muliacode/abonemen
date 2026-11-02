<?php

namespace Tests\Feature\Households;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HouseholdSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_can_update_the_household_settings(): void
    {
        [$user, $household] = $this->userWithRole(HouseholdRole::Owner);

        $this->actingAs($user);

        Livewire::test('pages::households.edit', ['household' => $household])
            ->set('name', 'Renamed')
            ->set('mainCurrency', 'USD')
            ->set('timezone', 'America/New_York')
            ->call('updateHousehold')
            ->assertHasNoErrors();

        $household->refresh();

        $this->assertSame('Renamed', $household->name);
        $this->assertSame('USD', $household->main_currency);
        $this->assertSame('America/New_York', $household->timezone);
    }

    public function test_an_editor_cannot_update_the_household_settings(): void
    {
        [$user, $household] = $this->userWithRole(HouseholdRole::Editor);

        $this->actingAs($user);

        Livewire::test('pages::households.edit', ['household' => $household])
            ->set('name', 'Renamed')
            ->call('updateHousehold')
            ->assertForbidden();

        $this->assertNotSame('Renamed', $household->fresh()->name);
    }

    public function test_a_viewer_cannot_update_the_household_settings(): void
    {
        [$user, $household] = $this->userWithRole(HouseholdRole::Viewer);

        $this->actingAs($user);

        Livewire::test('pages::households.edit', ['household' => $household])
            ->set('name', 'Renamed')
            ->call('updateHousehold')
            ->assertForbidden();

        $this->assertNotSame('Renamed', $household->fresh()->name);
    }

    public function test_members_without_manage_rights_see_the_settings_read_only(): void
    {
        [$user, $household] = $this->userWithRole(HouseholdRole::Viewer);

        $this->actingAs($user)
            ->get(route('households.edit', $household))
            ->assertOk()
            ->assertSee($household->name)
            ->assertDontSeeHtml('data-test="household-save-button"');
    }

    public function test_the_settings_page_is_not_available_to_people_outside_the_household(): void
    {
        [, $household] = $this->userWithRole(HouseholdRole::Owner);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('households.edit', $household))
            ->assertForbidden();
    }

    public function test_invalid_settings_are_rejected(): void
    {
        [$user, $household] = $this->userWithRole(HouseholdRole::Owner);

        $this->actingAs($user);

        Livewire::test('pages::households.edit', ['household' => $household])
            ->set('name', '')
            ->set('mainCurrency', 'XXL')
            ->set('timezone', 'Mars/Olympus')
            ->call('updateHousehold')
            ->assertHasErrors(['name', 'mainCurrency', 'timezone']);
    }

    /**
     * @return array{0: User, 1: Household}
     */
    private function userWithRole(HouseholdRole $role): array
    {
        $user = User::factory()->create();
        $household = Household::factory()->withUser($user, $role)->create();

        return [$user, $household];
    }
}
