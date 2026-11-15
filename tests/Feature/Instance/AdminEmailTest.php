<?php

namespace Tests\Feature\Instance;

use App\Mail\TestMail;
use App\Models\User;
use App\Support\MailConfiguration;
use App\Support\PlatformSettings;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Email page sets up SMTP so that password resets and verification emails reach people. What is saved here
 * wins over .env, the password is never shown again, and a test email proves the settings work.
 */
class AdminEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->instanceAdmin()->create(['email' => 'ada@example.com']);
        $this->actingAs($this->admin);
    }

    /**
     * @return array<string, mixed>
     */
    private function smtp(array $overrides = []): array
    {
        return [
            'fromName' => 'Abonemen at Home',
            'fromAddress' => 'abonemen@home.example',
            'host' => 'smtp.home.example',
            'port' => 587,
            'scheme' => 'smtp',
            'username' => 'abonemen',
            'password' => 'secret-smtp-password',
            ...$overrides,
        ];
    }

    private function fill(Testable $page, array $values): Testable
    {
        foreach ($values as $property => $value) {
            $page->set($property, $value);
        }

        return $page;
    }

    public function test_the_page_says_that_emails_are_not_delivered_until_something_is_set_up(): void
    {
        $this->assertSame('undelivered', app(MailConfiguration::class)->source());

        Livewire::test('pages::admin.email')
            ->assertSee(__('Emails are not delivered yet'))
            ->assertSet('source', 'undelivered');
    }

    public function test_saved_settings_are_used_for_sending_and_say_so(): void
    {
        $page = $this->fill(Livewire::test('pages::admin.email'), $this->smtp())->call('save')->assertHasNoErrors();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.home.example', config('mail.mailers.smtp.host'));
        $this->assertSame(587, (int) config('mail.mailers.smtp.port'));
        $this->assertSame('abonemen', config('mail.mailers.smtp.username'));
        $this->assertSame('secret-smtp-password', config('mail.mailers.smtp.password'));
        $this->assertSame('abonemen@home.example', config('mail.from.address'));
        $this->assertSame('Abonemen at Home', config('mail.from.name'));

        $page->assertSet('source', 'saved')->assertSee(__('Using the settings saved here'));
    }

    public function test_what_is_saved_is_applied_again_on_the_next_boot(): void
    {
        $this->fill(Livewire::test('pages::admin.email'), $this->smtp())->call('save');

        config(['mail.default' => 'log', 'mail.mailers.smtp.host' => '127.0.0.1']);

        app(MailConfiguration::class)->apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.home.example', config('mail.mailers.smtp.host'));
    }

    public function test_the_environment_applies_while_nothing_is_saved_and_a_real_mailer_there_counts(): void
    {
        config(['mail.default' => 'smtp']);

        $this->assertSame('env', app(MailConfiguration::class)->source());

        Livewire::test('pages::admin.email')->assertSee(__('Using the settings from .env'));
    }

    public function test_a_bad_setting_is_refused(): void
    {
        $this->fill(Livewire::test('pages::admin.email'), $this->smtp(['host' => '', 'port' => 70000, 'fromAddress' => 'not-an-email', 'scheme' => 'ssl']))
            ->call('save')
            ->assertHasErrors(['host', 'port', 'fromAddress', 'scheme']);

        $this->assertNull((new PlatformSettings)->mail());
    }

    public function test_the_password_is_never_shown_and_an_empty_one_keeps_the_saved_one(): void
    {
        $page = $this->fill(Livewire::test('pages::admin.email'), $this->smtp())->call('save');

        $page->assertSet('password', '')
            ->assertDontSee('secret-smtp-password')
            ->assertSee(__('A password is saved. Leave this empty to keep it.'));

        $page->set('host', 'smtp2.home.example')->set('password', '')->call('save')->assertHasNoErrors();

        $this->assertSame('smtp2.home.example', (new PlatformSettings)->mail()['host']);
        $this->assertSame('secret-smtp-password', (new PlatformSettings)->mail()['password']);

        $page->set('password', 'a-new-password')->call('save');

        $this->assertSame('a-new-password', (new PlatformSettings)->mail()['password']);
    }

    public function test_the_stored_settings_hold_nothing_readable(): void
    {
        $this->fill(Livewire::test('pages::admin.email'), $this->smtp())->call('save');

        $stored = DB::table('platform_settings')->where('key', 'mail')->value('value');

        foreach (['secret-smtp-password', 'smtp.home.example', 'abonemen@home.example'] as $plaintext) {
            $this->assertStringNotContainsString($plaintext, $stored);
        }
    }

    public function test_a_test_email_goes_to_the_admin_and_marks_the_settings_as_working(): void
    {
        Mail::fake();

        $page = $this->fill(Livewire::test('pages::admin.email'), $this->smtp())->call('save')->call('sendTest');

        Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('ada@example.com'));

        $page->assertSee(__('The test email was sent to :email.', ['email' => 'ada@example.com']));
        $this->assertTrue((new PlatformSettings)->mailTestSucceeded());
    }

    public function test_a_failed_test_shows_why_without_the_password_and_does_not_count(): void
    {
        Mail::shouldReceive('to')->andThrow(new Exception('Authentication failed for abonemen with secret-smtp-password'));
        Mail::shouldReceive('forgetMailers');

        $page = $this->fill(Livewire::test('pages::admin.email'), $this->smtp())->call('save')->call('sendTest');

        $page->assertSee('Authentication failed for abonemen')->assertDontSee('secret-smtp-password');
        $this->assertFalse((new PlatformSettings)->mailTestSucceeded());
    }

    public function test_changing_any_setting_after_a_good_test_means_it_has_to_be_tested_again(): void
    {
        Mail::fake();

        $page = $this->fill(Livewire::test('pages::admin.email'), $this->smtp())->call('save')->call('sendTest');
        $this->assertTrue((new PlatformSettings)->mailTestSucceeded());

        $page->set('port', 465)->call('save');

        $this->assertFalse((new PlatformSettings)->mailTestSucceeded());
    }

    public function test_going_back_to_the_environment_forgets_what_was_saved(): void
    {
        $fromEnvironment = config('mail.default');

        $page = $this->fill(Livewire::test('pages::admin.email'), $this->smtp())->call('save');
        $this->assertSame('smtp', config('mail.default'));

        $page->call('resetToEnvironment')->assertSet('source', 'undelivered');

        $this->assertNull((new PlatformSettings)->mail());
        $this->assertSame($fromEnvironment, config('mail.default'));
    }
}
