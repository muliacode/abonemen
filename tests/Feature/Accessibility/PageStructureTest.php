<?php

namespace Tests\Feature\Accessibility;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PageStructureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every screen of the application. A new screen is added here, so it is held to the same structure.
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

    #[DataProvider('pages')]
    public function test_every_page_starts_with_a_skip_link_to_its_main_landmark(string $page): void
    {
        $xpath = $this->render($page);

        $firstFocusable = $xpath->query('(//body//*[self::a[@href] or self::button or self::input or self::select or self::textarea])[1]')->item(0);

        $this->assertInstanceOf(DOMElement::class, $firstFocusable);
        $this->assertSame('#main', $firstFocusable->getAttribute('href'), 'The first focusable element is the skip link.');

        $main = $xpath->query('//*[@id="main"]');

        $this->assertCount(1, $main);
        $this->assertSame('main', $main->item(0)->nodeName, 'The skip link targets the <main> landmark.');
    }

    #[DataProvider('pages')]
    public function test_every_page_has_exactly_one_top_level_heading(string $page): void
    {
        $headings = $this->render($page)->query('//h1');

        $this->assertCount(1, $headings);
        $this->assertNotSame('', trim($headings->item(0)->textContent), 'The heading says what the page is.');
    }

    #[DataProvider('pages')]
    public function test_headings_never_skip_a_level(string $page): void
    {
        $previous = 0;

        foreach ($this->render($page)->query('//h1|//h2|//h3|//h4|//h5|//h6') as $heading) {
            $level = (int) substr($heading->nodeName, 1);

            $this->assertLessThanOrEqual($previous + 1, $level, "An <h{$level}> follows an <h{$previous}>.");

            $previous = $level;
        }
    }

    #[DataProvider('pages')]
    public function test_every_navigation_landmark_has_a_name(string $page): void
    {
        foreach ($this->render($page)->query('//nav') as $nav) {
            $this->assertTrue(
                $nav->hasAttribute('aria-label') || $nav->hasAttribute('aria-labelledby'),
                'A <nav> without a name cannot be told apart from the others.',
            );
        }
    }

    #[DataProvider('pages')]
    public function test_the_page_does_not_force_a_theme(string $page): void
    {
        $html = $this->render($page)->query('/html')->item(0);

        $this->assertStringNotContainsString('dark', $html->getAttribute('class'));
        $this->assertNotSame('', $html->getAttribute('lang'));
    }

    public function test_the_current_page_is_marked_in_the_sidebar(): void
    {
        $current = $this->render('dashboard')->query('//nav//a[@aria-current="page"]');

        $this->assertCount(1, $current);
    }

    /**
     * Render the page and hand back a way to query what a browser would parse.
     */
    private function render(string $page): DOMXPath
    {
        $dom = new DOMDocument;

        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$this->visit($page)->assertOk()->getContent());
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
