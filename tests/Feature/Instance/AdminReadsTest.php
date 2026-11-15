<?php

namespace Tests\Feature\Instance;

use App\Actions\Households\AddUserToHousehold;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An Instance Admin sees how many Households there are and how big they are, and nothing about what is inside them
 * or who the people in them are (ADR 0003).
 */
class AdminReadsTest extends TestCase
{
    use RefreshDatabase;

    private function signInAsAdmin(): void
    {
        $this->actingAs(User::factory()->instanceAdmin()->create());
    }

    public function test_the_households_list_shows_name_date_and_number_of_users_newest_first(): void
    {
        $this->signInAsAdmin();

        $older = Household::factory()->withUser(User::factory()->create())->create(['name' => 'Villa Zonnebloem', 'created_at' => now()->subDays(3)]);
        $newer = Household::factory()->create(['name' => 'Appartement Dekker', 'created_at' => now()->subDay()]);
        foreach (User::factory()->count(2)->create() as $user) {
            app(AddUserToHousehold::class)->handle($newer, $user, HouseholdRole::Viewer);
        }
        $newer->members()->create(['name' => 'Kleine Fenna']); // a Member without a sign-in is not a User

        $page = Livewire::test('pages::admin.households');

        $page->assertSeeInOrder(['Appartement Dekker', now()->subDay()->format('Y-m-d'), '2', 'Villa Zonnebloem', now()->subDays(3)->format('Y-m-d'), '1']);
        $page->assertDontSee('Kleine Fenna');
    }

    public function test_the_households_list_has_25_to_a_page(): void
    {
        $this->signInAsAdmin();
        Household::factory()->count(30)->create();

        $page = Livewire::test('pages::admin.households');

        $this->assertCount(25, $page->get('households')->items());
        $this->assertSame(30, $page->get('households')->total());
    }
}
