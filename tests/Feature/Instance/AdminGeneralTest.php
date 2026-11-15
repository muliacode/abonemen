<?php

namespace Tests\Feature\Instance;

use App\Enums\Locale;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The General page holds the choices that shape the whole instance: who can register, whether the admin section
 * needs a second factor, and the defaults for new Households.
 */
class AdminGeneralTest extends TestCase
{
    use RefreshDatabase;

    private PlatformSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = app(PlatformSettings::class);
        $this->actingAs(User::factory()->instanceAdmin()->create());
        $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_the_page_starts_from_the_safe_choices(): void
    {
        Livewire::test('pages::admin.general')
            ->assertSet('registrationOpen', false)
            ->assertSet('requireSecondFactor', true)
            ->assertSet('currency', 'EUR')
            ->assertSet('timezone', config('app.timezone'));
    }

    public function test_the_page_shows_what_is_saved(): void
    {
        $this->settings->openRegistration(true);
        $this->settings->setRequireSecondFactor(false);
        $this->settings->setDefaults(Locale::Dutch, 'IDR', 'Asia/Jakarta');

        Livewire::test('pages::admin.general')
            ->assertSet('registrationOpen', true)
            ->assertSet('requireSecondFactor', false)
            ->assertSet('language', 'nl')
            ->assertSet('currency', 'IDR')
            ->assertSet('timezone', 'Asia/Jakarta');
    }

    public function test_an_admin_opens_and_closes_registration(): void
    {
        $page = Livewire::test('pages::admin.general');

        $page->set('registrationOpen', true)->call('save')->assertHasNoErrors();
        $this->assertTrue($this->settings->registrationIsOpen());

        $page->set('registrationOpen', false)->call('save')->assertHasNoErrors();
        $this->assertFalse($this->settings->registrationIsOpen());
    }

    public function test_dropping_the_second_factor_requirement_needs_the_admins_password(): void
    {
        foreach (['', 'not-my-password'] as $password) {
            Livewire::test('pages::admin.general')
                ->set('requireSecondFactor', false)
                ->set('password', $password)
                ->call('save')
                ->assertHasErrors('password');

            $this->assertTrue((new PlatformSettings)->requireSecondFactor(), 'Lowering a protection needs the password.');
        }

        Livewire::test('pages::admin.general')
            ->set('requireSecondFactor', false)
            ->set('password', 'password')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('password', '');

        $this->assertFalse((new PlatformSettings)->requireSecondFactor());
    }

    public function test_the_password_check_cannot_be_skipped_by_editing_the_page_from_the_browser(): void
    {
        try {
            Livewire::test('pages::admin.general')->set('wasRequired', false);

            $this->fail('wasRequired can be set from the browser, which would skip the password for lowering a protection.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_requiring_a_second_factor_again_and_saving_other_choices_need_no_password(): void
    {
        $this->settings->setRequireSecondFactor(false);

        Livewire::test('pages::admin.general')
            ->set('requireSecondFactor', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue((new PlatformSettings)->requireSecondFactor());

        Livewire::test('pages::admin.general')
            ->set('registrationOpen', true)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_the_defaults_for_new_households_are_saved(): void
    {
        Livewire::test('pages::admin.general')
            ->set('language', 'id')
            ->set('currency', 'IDR')
            ->set('timezone', 'Asia/Jakarta')
            ->call('save')
            ->assertHasNoErrors();

        $settings = new PlatformSettings;

        $this->assertSame(Locale::Indonesian, $settings->defaultLanguage());
        $this->assertSame('IDR', $settings->defaultCurrency());
        $this->assertSame('Asia/Jakarta', $settings->defaultTimezone());
    }

    public function test_only_languages_currencies_and_time_zones_that_exist_are_accepted(): void
    {
        Livewire::test('pages::admin.general')
            ->set('language', 'xx')
            ->set('currency', 'ZZZ')
            ->set('timezone', 'Mars/Olympus')
            ->call('save')
            ->assertHasErrors(['language', 'currency', 'timezone']);

        $this->assertSame('EUR', (new PlatformSettings)->defaultCurrency());
    }

    public function test_a_change_is_logged_with_who_made_it_and_never_with_the_value(): void
    {
        Log::spy();

        Livewire::test('pages::admin.general')->set('registrationOpen', true)->call('save');

        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $message === 'Instance setting changed.'
            && $context['setting'] === 'registration_open'
            && $context['user_id'] === auth()->id()
            && ! array_key_exists('value', $context))->once();
    }

    public function test_the_admin_section_has_a_page_for_each_of_its_tasks(): void
    {
        foreach (['instance.households', 'instance.users', 'instance.general'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_the_sidebar_offers_the_admin_section_only_to_admins(): void
    {
        $this->get(route('instance.households'))->assertSee('data-test="instance-admin-link"', false);

        $this->actingAs(User::factory()->withHousehold()->create())
            ->get(route('profile.edit'))
            ->assertDontSee('data-test="instance-admin-link"', false)
            ->assertDontSee(config('abonemen.admin_path').'/', false);
    }
}
