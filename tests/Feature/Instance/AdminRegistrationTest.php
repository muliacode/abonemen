<?php

namespace Tests\Feature\Instance;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->instanceAdmin()->create());
    }

    public function test_an_admin_opens_and_closes_registration(): void
    {
        $page = Livewire::test('pages::admin.registration')->assertSet('open', false);

        $page->set('open', true);
        $this->assertTrue(app(PlatformSettings::class)->registrationIsOpen());

        $page->set('open', false);
        $this->assertFalse(app(PlatformSettings::class)->registrationIsOpen());
    }

    public function test_the_page_shows_the_current_state(): void
    {
        app(PlatformSettings::class)->openRegistration(true);

        Livewire::test('pages::admin.registration')->assertSet('open', true);
    }

    public function test_the_admin_section_has_a_page_for_each_of_its_tasks(): void
    {
        foreach (['instance.households', 'instance.users', 'instance.registration'] as $route) {
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
