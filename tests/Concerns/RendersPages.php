<?php

namespace Tests\Concerns;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;

/**
 * Renders every screen of the application and lets a test query what a browser would parse.
 */
trait RendersPages
{
    /**
     * Every screen of the application. A new screen is added here, so it is held to the same rules.
     *
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return array_combine(
            $names = [
                'welcome', 'login', 'register', 'forgot password', 'reset password', 'two-factor challenge',
                'verify email', 'confirm password', 'dashboard', 'profile', 'security', 'appearance', 'language',
                'households', 'household settings',
            ],
            array_map(fn (string $name) => [$name], $names),
        );
    }

    /**
     * Render the page and hand back a way to query what a browser would parse.
     */
    private function render(string $page): DOMXPath
    {
        return $this->parse($this->visit($page)->assertOk()->getContent());
    }

    /**
     * Parse rendered HTML the way a browser would, tolerating the custom elements of Flux.
     */
    private function parse(string $html): DOMXPath
    {
        $dom = new DOMDocument;

        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    private function visit(string $page): TestResponse
    {
        $user = User::factory()->withHousehold()->create();

        return match ($page) {
            'welcome' => $this->get(route('home')),
            'login' => $this->get(route('login')),
            'register' => $this->get(route('register')),
            'forgot password' => $this->get(route('password.request')),
            'reset password' => $this->get(route('password.reset', ['token' => 'a-token'])),
            'two-factor challenge' => $this->twoFactorChallenge(),
            'verify email' => $this->actingAs(User::factory()->unverified()->create())->get(route('verification.notice')),
            'confirm password' => $this->actingAs($user)->get(route('password.confirm')),
            'dashboard' => $this->actingAs($user)->get(route('dashboard')),
            'profile' => $this->actingAs($user)->get(route('profile.edit')),
            'security' => $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit')),
            'appearance' => $this->actingAs($user)->get(route('appearance.edit')),
            'language' => $this->actingAs($user)->get(route('language.edit')),
            'households' => $this->actingAs($user)->get(route('households.index')),
            'household settings' => $this->actingAs($user)->get(route('households.edit', $user->currentHousehold)),
        };
    }

    private function twoFactorChallenge(): TestResponse
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        return $this->get(route('two-factor.login'));
    }
}
