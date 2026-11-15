<?php

namespace Tests\Feature\Instance;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The Server page tells an Instance Admin what the app runs on, in plain words, and warns only where something
 * is wrong. It never shows a secret and never writes .env.
 */
class AdminServerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->instanceAdmin()->create());
    }

    /**
     * Visit the page at the given address. The test client otherwise visits APP_URL itself, which would never differ.
     */
    private function visit(string $origin = 'http://localhost'): TestResponse
    {
        return $this->get($origin.'/'.config('abonemen.admin_path').'/server')->assertOk();
    }

    private function page(string $origin = 'http://localhost'): string
    {
        return $this->visit($origin)->getContent();
    }

    public function test_the_page_lists_what_the_app_runs_on(): void
    {
        $this->get(route('instance.server'))
            ->assertOk()
            ->assertSeeInOrder([__('Address'), __('Database'), 'SQLite', __('Master key'), __('Application key'), __('Email'), __('Queue'), __('Cache'), 'PHP '.PHP_VERSION]);
    }

    public function test_an_address_that_matches_app_url_raises_no_warning(): void
    {
        config(['app.url' => 'http://localhost']);

        $this->assertStringNotContainsString('data-test="address-warning"', $this->page('http://localhost'));
    }

    public function test_an_address_that_differs_from_app_url_warns_and_gives_the_line_to_change(): void
    {
        config(['app.url' => 'https://abonemen.example.com']);

        $this->visit('http://localhost')
            ->assertSee('data-test="address-warning"', false)
            ->assertSee('APP_URL=http://localhost');
    }

    public function test_a_trailing_slash_or_path_in_app_url_is_not_a_difference(): void
    {
        config(['app.url' => 'http://localhost/']);

        $this->assertStringNotContainsString('data-test="address-warning"', $this->page());
    }

    public function test_the_email_row_follows_where_the_settings_come_from(): void
    {
        $this->get(route('instance.server'))->assertSee(__('Emails are not delivered yet'));

        app(PlatformSettings::class)->saveMail([
            'from_name' => 'Abonemen', 'from_address' => 'post@abonemen.example', 'host' => 'smtp.private.example',
            'port' => 587, 'scheme' => 'smtp', 'username' => 'smtp-user-name', 'password' => 'smtp-secret-pw',
        ]);

        $this->get(route('instance.server'))->assertSee(__('Using the settings saved here'));
    }

    public function test_no_secret_ever_reaches_the_page(): void
    {
        app(PlatformSettings::class)->saveMail([
            'from_name' => 'Abonemen', 'from_address' => 'post@abonemen.example', 'host' => 'smtp.private.example',
            'port' => 587, 'scheme' => 'smtp', 'username' => 'smtp-user-name', 'password' => 'smtp-secret-pw',
        ]);

        $page = $this->page();

        foreach ([
            config('protection.master_key'),
            substr((string) config('protection.master_key'), 7),
            (string) config('app.key'),
            'smtp-secret-pw', 'smtp-user-name', 'smtp.private.example',
            (string) config('database.connections.sqlite.database'),
        ] as $secret) {
            if ($secret !== '') {
                $this->assertStringNotContainsString($secret, $page);
            }
        }
    }
}
