<?php

namespace Tests\Feature\Localisation;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_language_that_is_no_longer_offered_does_not_lock_the_user_out(): void
    {
        $user = User::factory()->create();

        // A language removed from the app later, or a value written by hand.
        DB::table('users')->where('id', $user->id)->update(['locale' => 'xx']);

        $this->actingAs($user->fresh())
            ->get(route('households.index'))
            ->assertOk()
            ->assertSee('lang="en"', false);

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_the_page_title_is_in_the_users_language_too(): void
    {
        $user = User::factory()->create(['locale' => 'nl']);

        $html = $this->actingAs($user)->get(route('households.index'))->getContent();

        $this->assertMatchesRegularExpression('#<title>\s*Huishoudens - #', $html);
    }

    public function test_interactive_requests_use_the_same_language_as_page_loads(): void
    {
        $route = Route::getRoutes()->getByName('default-livewire.update');

        $this->assertNotNull($route, 'The Livewire update route should exist.');
        $this->assertContains(SetLocale::class, app('router')->gatherRouteMiddleware($route));
    }

    public function test_english_is_the_fallback_when_a_language_lacks_a_text(): void
    {
        $user = User::factory()->create(['locale' => 'nl']);

        $this->actingAs($user)->get(route('households.index'));

        $this->assertSame('nl', app()->getLocale());
        $this->assertSame('en', config('app.fallback_locale'));
        $this->assertSame('A text no language has translated yet', __('A text no language has translated yet'));
    }

    public function test_a_users_saved_language_applies_to_their_screens(): void
    {
        $user = User::factory()->create(['locale' => 'nl']);

        $this->actingAs($user)
            ->get(route('households.index'))
            ->assertOk()
            ->assertSee('Huishoudens')
            ->assertSee('lang="nl"', false);
    }
}
