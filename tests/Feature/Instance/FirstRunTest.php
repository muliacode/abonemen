<?php

namespace Tests\Feature\Instance;

use App\Enums\HouseholdRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * A new instance has nobody who can run it. The person who installed it proves that with the setup token
 * they put in .env, and becomes the first Instance Admin. After that the door is gone.
 */
class FirstRunTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'a-long-secret-the-installer-chose';

    protected function setUp(): void
    {
        parent::setUp();

        config(['abonemen.setup_token' => self::TOKEN]);
    }

    /**
     * @return array<string, string>
     */
    private function details(array $overrides = []): array
    {
        return [
            'token' => self::TOKEN,
            'name' => 'Ada Admin',
            'email' => 'ada@example.com',
            'password' => 'a-good-password-1',
            'password_confirmation' => 'a-good-password-1',
            ...$overrides,
        ];
    }

    public function test_the_installer_becomes_a_verified_instance_admin_and_is_signed_in(): void
    {
        $this->post(route('setup.store'), $this->details())
            ->assertSessionHasNoErrors();

        $admin = User::whereEmail('ada@example.com')->sole();

        $this->assertTrue($admin->is_instance_admin);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_the_installer_also_gets_a_household_of_their_own_like_anyone_who_registers(): void
    {
        $response = $this->post(route('setup.store'), $this->details());

        $admin = User::whereEmail('ada@example.com')->sole();
        $household = $admin->currentHousehold;

        $response->assertRedirect(route('dashboard', ['current_household' => $household->ulid], absolute: false));

        $this->assertSame("Ada Admin's household", $household->name);
        $this->assertSame(HouseholdRole::Owner, $admin->householdRole($household));
        $this->assertDatabaseCount('households', 1);
    }

    public function test_the_setup_page_is_there_while_the_instance_has_no_admin(): void
    {
        $this->get(route('setup'))
            ->assertOk()
            ->assertSee(__('Set up Abonemen'));
    }

    public function test_a_wrong_or_missing_token_creates_nobody(): void
    {
        foreach (['not-the-token', ''] as $token) {
            $this->post(route('setup.store'), $this->details(['token' => $token]))
                ->assertSessionHasErrors('token');
        }

        $this->post(route('setup.store'), Arr::except($this->details(), 'token'))
            ->assertSessionHasErrors('token');

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_once_there_is_an_instance_admin_setup_is_gone_even_with_the_right_token(): void
    {
        User::factory()->create(['is_instance_admin' => true]);

        $this->post(route('setup.store'), $this->details())->assertNotFound();
        $this->get(route('setup'))->assertNotFound();

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_without_a_configured_token_setup_does_not_exist(): void
    {
        config(['abonemen.setup_token' => null]);

        $this->post(route('setup.store'), $this->details(['token' => '']))->assertNotFound();
        $this->post(route('setup.store'), $this->details(['token' => null]))->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }
}
