<?php

namespace Tests\Feature\Instance;

use App\Enums\Connection;
use App\Models\Household;
use App\Models\HouseholdConnection;
use App\Models\User;
use App\Support\Connections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Connections page lists every outbound call and whether it is on, so that an Instance Admin can check that
 * nothing leaves the server unexpectedly. It shows how many Households use a Connection, never which.
 */
class AdminConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->instanceAdmin()->create());
    }

    private function mailDelivers(string $host = 'smtp.home.example'): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => $host]);
    }

    public function test_email_to_households_is_off_until_the_admin_makes_it_available(): void
    {
        $this->mailDelivers();

        Livewire::test('pages::admin.connections')
            ->assertSee(__('Email to Households'))
            ->assertSet('emailAvailable', false)
            ->call('makeEmailAvailable')
            ->assertSet('emailAvailable', true);

        $this->assertTrue(app(Connections::class)->isAvailable(Connection::Email));
    }

    public function test_the_admin_can_withdraw_email_again(): void
    {
        $this->mailDelivers();
        app(Connections::class)->makeAvailable(Connection::Email);

        Livewire::test('pages::admin.connections')
            ->assertSet('emailAvailable', true)
            ->call('makeEmailUnavailable')
            ->assertSet('emailAvailable', false);

        $this->assertFalse(app(Connections::class)->isAvailable(Connection::Email));
    }

    public function test_email_cannot_be_made_available_while_nobody_would_receive_it(): void
    {
        Livewire::test('pages::admin.connections')
            ->assertSee(__('Set up email first. Until then nobody would receive these emails.'))
            ->call('makeEmailAvailable')
            ->assertSet('emailAvailable', false);

        $this->assertFalse(app(Connections::class)->isAvailable(Connection::Email));
    }

    public function test_the_page_counts_households_and_never_names_them(): void
    {
        $this->mailDelivers();
        $connections = app(Connections::class);
        $connections->makeAvailable(Connection::Email);

        $owner = User::factory()->create();
        $current = Household::factory()->count(2)->create();
        $stale = Household::factory()->create(['name' => 'The Visserman Family']);

        foreach ([...$current, $stale] as $household) {
            $connections->confirm(Connection::Email, $household, $owner);
        }

        // One Owner confirmed an older disclosure, so their Household waits for them to confirm again.
        HouseholdConnection::where('household_id', $stale->id)->update(['confirmed_version' => 0]);

        Livewire::test('pages::admin.connections')
            ->assertSet('householdsOn', 2)
            ->assertSet('householdsWaiting', 1)
            ->assertDontSee('The Visserman Family')
            ->assertDontSee($current[0]->name);
    }

    public function test_account_emails_are_listed_but_have_no_switch(): void
    {
        $this->mailDelivers();

        Livewire::test('pages::admin.connections')
            ->assertSee(__('Account emails'))
            ->assertSee(__('On because email is set up'));
    }

    public function test_the_change_is_announced_in_a_live_region(): void
    {
        $this->mailDelivers();

        Livewire::test('pages::admin.connections')
            ->assertSet('status', null)
            ->call('makeEmailAvailable')
            ->assertSet('status', __('Saved.'))
            ->assertSeeHtml('data-status-message')
            ->call('makeEmailUnavailable')
            ->assertSet('status', __('Saved.'));
    }

    public function test_nothing_is_announced_when_email_could_not_be_made_available(): void
    {
        Livewire::test('pages::admin.connections')
            ->call('makeEmailAvailable')
            ->assertSet('status', null);
    }
}
