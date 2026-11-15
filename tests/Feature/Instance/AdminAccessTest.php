<?php

namespace Tests\Feature\Instance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Instance Admin section does not exist for anyone who is not an Instance Admin, and Instance Admins
 * enter it only with a second factor, so that a stolen password alone does not open it.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->is_instance_admin = true;
        $admin->save();

        return $admin;
    }

    public function test_a_guest_finds_nothing(): void
    {
        $this->get(route('instance.households'))->assertNotFound();
    }

    public function test_a_member_finds_nothing(): void
    {
        $this->actingAs(User::factory()->withHousehold()->create())
            ->get(route('instance.households'))
            ->assertNotFound();
    }

    public function test_an_admin_without_a_second_factor_is_sent_to_set_one_up(): void
    {
        $this->actingAs($this->admin())
            ->get(route('instance.households'))
            ->assertRedirect(route('security.edit'))
            ->assertSessionHas('status');
    }

    public function test_an_admin_with_two_factor_enters(): void
    {
        $admin = $this->admin();
        $admin->forceFill(User::factory()->withTwoFactor()->make()->only(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']))->save();

        $this->actingAs($admin)->get(route('instance.households'))->assertOk();
    }

    public function test_an_admin_whose_two_factor_setup_is_unconfirmed_does_not_enter(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => null])->save();

        $this->actingAs($admin)->get(route('instance.households'))->assertRedirect(route('security.edit'));
    }

    public function test_an_admin_with_a_passkey_enters(): void
    {
        $admin = $this->admin();
        $admin->passkeys()->forceCreate(['user_id' => $admin->id, 'name' => 'Laptop', 'credential_id' => 'abc', 'credential' => []]);

        $this->actingAs($admin)->get(route('instance.households'))->assertOk();
    }
}
