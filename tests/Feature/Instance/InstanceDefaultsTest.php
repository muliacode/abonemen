<?php

namespace Tests\Feature\Instance;

use App\Enums\Locale;
use App\Models\Household;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * What the Instance Admin chose on the General page is what new Households start with, and the language
 * a visitor gets when nobody has picked one.
 */
class InstanceDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function chooseDefaults(): void
    {
        app(PlatformSettings::class)->setDefaults(Locale::Dutch, 'IDR', 'Asia/Jakarta');
    }

    public function test_a_household_made_for_a_new_user_follows_the_instance_defaults(): void
    {
        $this->openRegistration();
        $this->chooseDefaults();

        $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $household = User::whereEmail('test@example.com')->sole()->currentHousehold;

        $this->assertSame('IDR', $household->main_currency);
        $this->assertSame('Asia/Jakarta', $household->timezone);
    }

    public function test_the_new_household_dialog_starts_from_the_instance_defaults(): void
    {
        $this->chooseDefaults();

        Livewire::actingAs(User::factory()->withHousehold()->create())
            ->test('create-household-modal')
            ->assertSet('mainCurrency', 'IDR')
            ->assertSet('timezone', 'Asia/Jakarta');
    }

    public function test_changing_the_defaults_leaves_the_households_that_exist_alone(): void
    {
        $household = Household::factory()->create();

        $this->chooseDefaults();

        $household->refresh();

        $this->assertSame('EUR', $household->main_currency);
        $this->assertSame('Europe/Amsterdam', $household->timezone);
    }

    public function test_a_visitor_without_a_chosen_or_browser_language_gets_the_instance_default(): void
    {
        $this->chooseDefaults();

        $this->withHeader('Accept-Language', 'ja')->get(route('login'))->assertSee('lang="nl"', false);
    }

    public function test_saving_one_choice_never_makes_the_others_forget_what_is_stored(): void
    {
        app(PlatformSettings::class)->openRegistration(true);

        // A new request: nothing has been read yet when the second choice is saved.
        $later = new PlatformSettings;
        $later->setRequireSecondFactor(false);

        $this->assertTrue($later->registrationIsOpen());
        $this->assertTrue((new PlatformSettings)->registrationIsOpen());
    }

    public function test_the_browser_language_and_the_users_own_choice_come_before_the_instance_default(): void
    {
        $this->chooseDefaults();

        $this->withHeader('Accept-Language', 'de')->get(route('login'))->assertSee('lang="de"', false);

        $user = User::factory()->withHousehold()->create(['locale' => 'fr']);

        $this->actingAs($user)->withHeader('Accept-Language', 'de')->get(route('profile.edit'))->assertSee('lang="fr"', false);
    }
}
