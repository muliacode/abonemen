<?php

namespace Tests\Feature\Localisation;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChangeLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_language_page_offers_every_language_by_its_own_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('language.edit'))
            ->assertOk()
            ->assertSee('English')
            ->assertSee('Nederlands')
            ->assertSee('Bahasa Indonesia')
            ->assertSee('Français')
            ->assertSee('Deutsch');
    }

    public function test_a_user_can_choose_their_language(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::settings.language')
            ->set('locale', 'fr')
            ->call('updateLanguage')
            ->assertHasNoErrors()
            ->assertRedirect(route('language.edit'));

        $this->assertSame(Locale::French, $user->fresh()->locale);
    }

    public function test_the_chosen_language_applies_to_the_next_screen(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::settings.language')
            ->set('locale', 'de')
            ->call('updateLanguage');

        $this->actingAs($user->fresh())
            ->get(route('households.index'))
            ->assertSee('Haushalte')
            ->assertSee('lang="de"', false);
    }

    public function test_the_page_starts_on_the_users_current_language(): void
    {
        $user = User::factory()->create(['locale' => 'id']);

        Livewire::actingAs($user)
            ->test('pages::settings.language')
            ->assertSet('locale', 'id');
    }

    public function test_a_language_the_app_is_not_available_in_is_refused(): void
    {
        $user = User::factory()->create(['locale' => 'nl']);

        Livewire::actingAs($user)
            ->test('pages::settings.language')
            ->set('locale', 'xx')
            ->call('updateLanguage')
            ->assertHasErrors('locale');

        $this->assertSame(Locale::Dutch, $user->fresh()->locale);
    }

    public function test_a_user_who_never_chose_sees_the_default_language_selected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::settings.language')
            ->assertSet('locale', 'en');
    }
}
