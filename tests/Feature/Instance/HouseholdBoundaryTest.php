<?php

namespace Tests\Feature\Instance;

use App\Enums\HouseholdPermission;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Being an Instance Admin gives no access to a Household (ADR 0003). The boundary is policy: nothing in the
 * application treats an admin as more than an outsider. Each feature that adds a Household route adds it to
 * routes() so this keeps proving it.
 */
class HouseholdBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Household $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->instanceAdmin()->withHousehold()->create();
        $this->stranger = Household::factory()->withUser(User::factory()->create())->create(['name' => 'Villa Zonnebloem']);
    }

    /**
     * The routes of a Household, which only its Members can open. A new feature adds its own.
     *
     * @return list<string>
     */
    private function routes(): array
    {
        return [
            route('dashboard', ['current_household' => $this->stranger->ulid]),
            route('households.edit', $this->stranger),
        ];
    }

    public function test_an_instance_admin_who_is_not_a_member_cannot_open_the_households_of_others(): void
    {
        foreach ($this->routes() as $url) {
            $this->actingAs($this->admin)->get($url)->assertForbidden();
        }
    }

    public function test_an_instance_admin_does_not_switch_into_a_household_they_do_not_belong_to(): void
    {
        $this->actingAs($this->admin);
        $home = $this->admin->currentHousehold;

        Livewire::test('household-switcher')
            ->assertDontSee('Villa Zonnebloem')
            ->call('switchHousehold', $this->stranger->ulid);

        $this->assertTrue($this->admin->fresh()->isCurrentHousehold($home));
    }

    public function test_an_instance_admin_holds_no_household_permission_outside_their_own_households(): void
    {
        foreach (HouseholdPermission::cases() as $permission) {
            $this->assertFalse($this->admin->hasHouseholdPermission($this->stranger, $permission), "{$permission->value} leaked to an admin.");
            $this->assertSame([], $this->admin->householdPermissionScopes($this->stranger, $permission));
        }
    }
}
