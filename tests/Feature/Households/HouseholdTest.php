<?php

namespace Tests\Feature\Households;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class HouseholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_household_and_becomes_its_owner_with_a_member(): void
    {
        $user = User::factory()->create(['name' => 'Sanne']);

        $this->actingAs($user);

        Livewire::test('create-household-modal')
            ->set('name', 'Home')
            ->set('mainCurrency', 'EUR')
            ->set('timezone', 'Europe/Amsterdam')
            ->call('createHousehold')
            ->assertHasNoErrors();

        $household = Household::where('name', 'Home')->sole();

        $this->assertSame('EUR', $household->main_currency);
        $this->assertSame('Europe/Amsterdam', $household->timezone);

        $this->assertDatabaseHas('members', [
            'household_id' => $household->id,
            'user_id' => $user->id,
            'name' => 'Sanne',
        ]);

        $this->assertSame(HouseholdRole::Owner, $user->householdRole($household));
        $this->assertTrue(Gate::forUser($user)->allows('update', $household));
    }

    public function test_the_new_household_becomes_the_current_household_and_opens_its_dashboard(): void
    {
        $user = User::factory()->withHousehold()->create();
        $previous = $user->currentHousehold;

        $this->actingAs($user);

        Livewire::test('create-household-modal')
            ->set('name', 'Second home')
            ->set('mainCurrency', 'USD')
            ->set('timezone', 'America/New_York')
            ->call('createHousehold')
            ->assertRedirect(route('dashboard', ['current_household' => Household::where('name', 'Second home')->sole()->ulid]));

        $this->assertTrue($user->fresh()->isCurrentHousehold(Household::where('name', 'Second home')->sole()));
        $this->assertFalse($user->fresh()->isCurrentHousehold($previous));
    }

    public function test_a_household_needs_a_name_a_real_currency_and_a_real_time_zone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('create-household-modal')
            ->set('name', '')
            ->set('mainCurrency', 'XXL')
            ->set('timezone', 'Mars/Olympus')
            ->call('createHousehold')
            ->assertHasErrors(['name', 'mainCurrency', 'timezone']);

        $this->assertDatabaseCount('households', 0);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_creating_a_household_does_not_touch_other_households(): void
    {
        $user = User::factory()->create();
        $other = Household::factory()->create();

        $this->actingAs($user);

        Livewire::test('create-household-modal')
            ->set('name', 'Home')
            ->call('createHousehold')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('households', 2);
        $this->assertSame(0, $other->members()->count());
    }

    public function test_no_visible_text_calls_a_household_a_team(): void
    {
        $user = User::factory()->withHousehold()->create();
        $household = $user->currentHousehold;

        $pages = [
            route('dashboard', ['current_household' => $household->ulid]),
            route('households.index'),
            route('households.edit', $household),
            route('profile.edit'),
            route('appearance.edit'),
        ];

        foreach ($pages as $page) {
            $html = $this->actingAs($user)->get($page)->assertOk()->getContent();
            $visible = strip_tags(preg_replace('#<(script|style)\b.*?</\1>#si', '', $html));

            $this->assertDoesNotMatchRegularExpression('/\bteams?\b/i', $visible, "A team label is still shown on {$page}");
        }

    }

    public function test_no_visible_text_on_the_sign_in_pages_calls_a_household_a_team(): void
    {
        foreach ([route('login'), route('register')] as $page) {
            $html = $this->get($page)->assertOk()->getContent();
            $visible = strip_tags(preg_replace('#<(script|style)\b.*?</\1>#si', '', $html));

            $this->assertDoesNotMatchRegularExpression('/\bteams?\b/i', $visible, "A team label is still shown on {$page}");
        }
    }
}
