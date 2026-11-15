<?php

namespace Tests\Feature\Instance;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Get started" lists what an Instance Admin still has to look at, only while it is open: email that does not
 * get delivered, and an address that differs from APP_URL. When nothing is left the block is gone.
 */
class AdminChecklistTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->instanceAdmin()->create();
        $this->actingAs($this->admin);
        $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function landing(string $origin = 'http://localhost'): TestResponse
    {
        config(['app.url' => $origin]);

        return $this->get($origin.'/'.config('abonemen.admin_path'))->assertOk();
    }

    private function saveMail(): void
    {
        app(PlatformSettings::class)->saveMail([
            'from_name' => 'Abonemen', 'from_address' => 'post@abonemen.example', 'host' => 'smtp.private.example',
            'port' => 587, 'scheme' => 'smtp', 'username' => null, 'password' => null,
        ]);
    }

    public function test_email_that_is_not_delivered_is_the_first_thing_to_look_at(): void
    {
        $this->landing()
            ->assertSee('data-test="get-started"', false)
            ->assertSee('data-test="checklist-email"', false)
            ->assertSee(__('Emails are not delivered yet'))
            ->assertDontSee('data-test="checklist-address"', false);
    }

    public function test_saved_settings_that_were_never_tested_ask_for_a_test(): void
    {
        $this->saveMail();

        $this->landing()
            ->assertSee('data-test="checklist-email"', false)
            ->assertSee(__('Send a test email to check your email settings'));
    }

    public function test_a_real_mailer_in_the_environment_counts_as_set_up(): void
    {
        config(['mail.default' => 'smtp']);

        $this->landing()->assertDontSee('data-test="checklist-email"', false);
    }

    public function test_a_successful_test_closes_the_email_item_and_with_nothing_left_the_block_is_gone(): void
    {
        Mail::fake();
        $this->saveMail();

        Livewire::test('pages::admin.email')->call('sendTest');

        $this->landing()
            ->assertDontSee('data-test="checklist-email"', false)
            ->assertDontSee('data-test="get-started"', false);
    }

    public function test_an_address_that_differs_from_app_url_is_listed_with_a_link_to_the_server_page(): void
    {
        config(['mail.default' => 'smtp']);
        config(['app.url' => 'https://abonemen.example.com']);

        $this->get('http://localhost/'.config('abonemen.admin_path'))
            ->assertSee('data-test="checklist-address"', false)
            ->assertSee(route('instance.server'), false);
    }
}
