<?php

namespace Tests\Feature\Auth;

use App\Enums\HouseholdRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function details(): array
    {
        return [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
    }

    public function test_registration_is_closed_until_an_instance_admin_opens_it(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee(__('Registration is closed'))
            ->assertDontSee('data-test="register-user-button"', false);

        $this->post(route('register.store'), $this->details())->assertForbidden();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('households', 0);
        $this->assertGuest();
    }

    public function test_registration_screen_can_be_rendered_when_registration_is_open(): void
    {
        $this->openRegistration();

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('data-test="register-user-button"', false);
    }

    public function test_the_register_links_appear_only_while_registration_is_open(): void
    {
        $this->get(route('home'))->assertDontSee('data-test="welcome-register-link"', false);
        $this->get(route('login'))->assertDontSee('data-test="register-link"', false);

        $this->openRegistration();

        $this->get(route('home'))->assertSee('data-test="welcome-register-link"', false);
        $this->get(route('login'))->assertSee('data-test="register-link"', false);
    }

    public function test_a_new_user_gets_a_household_of_their_own_and_lands_in_it(): void
    {
        $this->openRegistration();

        $response = $this->post(route('register.store'), $this->details());

        $user = User::whereEmail('test@example.com')->sole();
        $household = $user->currentHousehold;

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', ['current_household' => $household->ulid], absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame("John Doe's household", $household->name);
        $this->assertSame('EUR', $household->main_currency);
        $this->assertSame(config('app.timezone'), $household->timezone);
        $this->assertTrue($user->members()->sole()->household->is($household));
        $this->assertSame(HouseholdRole::Owner, $user->householdRole($household));
        $this->assertDatabaseCount('households', 1);
    }

    public function test_registering_does_not_make_anyone_an_instance_admin(): void
    {
        $this->openRegistration();

        $this->post(route('register.store'), $this->details());

        $this->assertFalse(User::whereEmail('test@example.com')->sole()->is_instance_admin);
    }
}
