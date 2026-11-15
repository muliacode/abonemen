<?php

namespace Tests\Feature\Instance;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * Every page of the section, so that a page added without the gate fails here.
     *
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return array_combine(
            $routes = ['instance.households', 'instance.users', 'instance.general', 'instance.email', 'instance.server'],
            array_map(fn (string $route) => [$route], $routes),
        );
    }

    #[DataProvider('pages')]
    public function test_every_page_of_the_section_keeps_the_same_doors(string $route): void
    {
        $this->get(route($route))->assertNotFound();

        $this->actingAs(User::factory()->withHousehold()->create())->get(route($route))->assertNotFound();

        $this->actingAs($this->admin())->get(route($route))->assertRedirect(route('security.edit'));

        // With a second factor, the admin still confirms their password, as for Security, and then they are in.
        $verified = User::factory()->instanceAdmin()->create();

        $this->actingAs($verified)->get(route($route))->assertRedirect(route('password.confirm'));

        $this->actingAs($verified)->withSession(['auth.password_confirmed_at' => time()])->get(route($route))->assertOk();
    }

    public function test_the_password_prompt_never_shows_to_someone_who_is_not_an_admin(): void
    {
        // A confirmation does not let a guest or a Member tell that the section exists: they still get a plain 404.
        $this->withSession(['auth.password_confirmed_at' => time()]);

        $this->get(route('instance.households'))->assertNotFound();
        $this->actingAs(User::factory()->withHousehold()->create())->get(route('instance.households'))->assertNotFound();
    }

    public function test_an_admin_without_a_second_factor_is_sent_to_set_one_up_before_being_asked_for_a_password(): void
    {
        $this->actingAs($this->admin())->get(route('instance.households'))->assertRedirect(route('security.edit'));
    }

    public function test_a_confirmation_that_has_run_out_is_asked_for_again(): void
    {
        $this->actingAs(User::factory()->instanceAdmin()->create())
            ->withSession(['auth.password_confirmed_at' => time() - config('auth.password_timeout') - 1])
            ->get(route('instance.households'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_after_confirming_the_admin_lands_on_the_page_they_asked_for(): void
    {
        $admin = User::factory()->instanceAdmin()->create();

        $this->actingAs($admin)->get(route('instance.general'))->assertRedirect(route('password.confirm'));

        $this->actingAs($admin)->post(route('password.confirm.store'), ['password' => 'password'])
            ->assertRedirect(route('instance.general'));
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
            ->assertRedirect(route('security.edit'));
    }

    /**
     * Security asks for the password first, which would swallow a message flashed by the redirect. So the page itself
     * says why an Instance Admin has been sent there, for as long as it is true.
     */
    public function test_the_security_page_tells_an_admin_without_a_second_factor_why_they_are_there(): void
    {
        $this->actingAs($this->admin())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertSee(__('Set up two-factor authentication or a passkey to open the Instance Admin section.'));
    }

    public function test_the_security_page_says_nothing_about_it_to_anyone_who_does_not_need_it(): void
    {
        $withTwoFactor = User::factory()->instanceAdmin()->create();
        $member = User::factory()->withHousehold()->create();

        foreach ([$withTwoFactor, $member] as $user) {
            $this->actingAs($user)
                ->withSession(['auth.password_confirmed_at' => time()])
                ->get(route('security.edit'))
                ->assertDontSee(__('Set up two-factor authentication or a passkey to open the Instance Admin section.'));
        }
    }

    public function test_when_the_instance_does_not_require_a_second_factor_an_admin_without_one_enters(): void
    {
        app(PlatformSettings::class)->setRequireSecondFactor(false);

        $this->actingAs($this->admin())->withSession(['auth.password_confirmed_at' => time()])->get(route('instance.households'))->assertOk();
    }

    public function test_the_security_page_stays_quiet_about_it_when_a_second_factor_is_not_required(): void
    {
        app(PlatformSettings::class)->setRequireSecondFactor(false);

        $this->actingAs($this->admin())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertDontSee(__('Set up two-factor authentication or a passkey to open the Instance Admin section.'));
    }

    public function test_an_admin_with_two_factor_enters(): void
    {
        $admin = $this->admin();
        $admin->forceFill(User::factory()->withTwoFactor()->make()->only(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']))->save();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('instance.households'))->assertOk();
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

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('instance.households'))->assertOk();
    }
}
