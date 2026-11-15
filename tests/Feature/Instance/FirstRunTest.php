<?php

namespace Tests\Feature\Instance;

use App\Enums\HouseholdRole;
use App\Enums\Locale;
use App\Models\User;
use App\Support\PlatformSettings;
use DOMDocument;
use DOMXPath;
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

    public function test_setup_keeps_the_safe_choices_when_none_are_made(): void
    {
        $this->post(route('setup.store'), $this->details());

        $settings = app(PlatformSettings::class);

        $this->assertTrue($settings->requireSecondFactor());
        $this->assertFalse($settings->registrationIsOpen());
    }

    public function test_the_installer_can_open_registration_and_drop_the_second_factor_requirement(): void
    {
        $this->post(route('setup.store'), $this->details(['require_second_factor' => '0', 'registration_open' => '1']));

        $settings = app(PlatformSettings::class);

        $this->assertFalse($settings->requireSecondFactor());
        $this->assertTrue($settings->registrationIsOpen());
    }

    public function test_the_defaults_for_new_households_come_from_what_the_installer_is_using(): void
    {
        $this->withHeader('Accept-Language', 'de')
            ->post(route('setup.store'), $this->details(['timezone' => 'Asia/Jakarta']));

        $settings = app(PlatformSettings::class);

        $this->assertSame(Locale::German, $settings->defaultLanguage());
        $this->assertSame('Asia/Jakarta', $settings->defaultTimezone());
        $this->assertSame('EUR', $settings->defaultCurrency());
    }

    public function test_a_time_zone_that_does_not_exist_falls_back_to_the_servers(): void
    {
        $this->post(route('setup.store'), $this->details(['timezone' => 'Mars/Olympus']))->assertSessionHasNoErrors();

        $this->assertSame(config('app.timezone'), app(PlatformSettings::class)->defaultTimezone());
    }

    public function test_the_setup_page_is_there_while_the_instance_has_no_admin(): void
    {
        $this->get(route('setup'))
            ->assertOk()
            ->assertSee(__('Set up Abonemen'));
    }

    public function test_the_setup_page_offers_the_safe_choices_ticked_the_right_way(): void
    {
        $page = new DOMXPath(tap(new DOMDocument, fn ($dom) => @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$this->get(route('setup'))->getContent())));

        $secondFactor = $page->query('//ui-checkbox[@name="require_second_factor"]');
        $registration = $page->query('//ui-checkbox[@name="registration_open"]');

        $this->assertCount(1, $secondFactor);
        $this->assertCount(1, $registration);
        $this->assertTrue($secondFactor->item(0)->hasAttribute('checked'), 'The second factor is required unless the installer unticks it.');
        $this->assertFalse($registration->item(0)->hasAttribute('checked'), 'Registration is closed unless the installer ticks it.');
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
