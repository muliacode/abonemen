<?php

namespace Tests\Feature\Households;

use App\Actions\Households\AddUserToHousehold;
use App\Enums\Connection;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdConnection;
use App\Models\User;
use App\Support\Connections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An Owner switches email on for their Household only after reading what it sends and where it goes, and can
 * switch it off at any time. See ADR 0007.
 */
class HouseholdConnectionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->household = Household::factory()->withUser($this->owner, HouseholdRole::Owner)->create();
    }

    private function mailDelivers(string $host = 'smtp.home.example'): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => $host]);
    }

    private function adminMakesEmailAvailable(): void
    {
        $this->mailDelivers();
        app(Connections::class)->makeAvailable(Connection::Email);
    }

    private function page(): Testable
    {
        return Livewire::actingAs($this->owner)->test('pages::households.connections', ['household' => $this->household]);
    }

    public function test_only_someone_who_manages_the_household_opens_the_page(): void
    {
        $this->actingAs($this->owner)->get(route('households.connections', $this->household))->assertOk();

        foreach ([HouseholdRole::Editor, HouseholdRole::Viewer] as $role) {
            $member = User::factory()->create();
            app(AddUserToHousehold::class)->handle($this->household, $member, $role);

            $this->actingAs($member)->get(route('households.connections', $this->household))->assertForbidden();
        }
    }

    public function test_while_the_admin_has_not_made_email_available_the_owner_cannot_switch_it_on(): void
    {
        $this->mailDelivers();

        $this->page()
            ->assertSet('state', 'unavailable')
            ->assertSee(__('Your Instance Admin has not made email available.'))
            ->set('confirmed', true)
            ->call('enable')
            ->assertSet('state', 'unavailable');

        $this->assertSame(0, HouseholdConnection::count());
    }

    public function test_the_owner_reads_what_is_sent_and_where_it_goes_before_switching_email_on(): void
    {
        $this->adminMakesEmailAvailable();

        $this->page()
            ->assertSet('state', 'off')
            ->assertSee(__('Invitations'))
            ->assertSee(__('Reminders'))
            ->assertSee('smtp.home.example');
    }

    public function test_email_is_not_switched_on_without_the_confirmation(): void
    {
        $this->adminMakesEmailAvailable();

        $this->page()
            ->call('enable')
            ->assertHasErrors('confirmed')
            ->assertSet('state', 'off');

        $this->assertSame(0, HouseholdConnection::count());
        $this->assertFalse(app(Connections::class)->allows(Connection::Email, $this->household));
    }

    public function test_with_the_confirmation_email_is_switched_on_and_what_was_confirmed_is_recorded(): void
    {
        $this->adminMakesEmailAvailable();

        $this->page()
            ->set('confirmed', true)
            ->call('enable')
            ->assertHasNoErrors()
            ->assertSet('state', 'on')
            ->assertSet('switchedOn', true);

        $this->assertTrue(app(Connections::class)->allows(Connection::Email, $this->household));

        $consent = HouseholdConnection::sole();
        $this->assertSame($this->owner->id, $consent->enabled_by_user_id);
        $this->assertSame('smtp.home.example', $consent->confirmed_destination);
        $this->assertSame(Connection::Email->disclosureVersion(), $consent->confirmed_version);
    }

    public function test_the_owner_can_switch_email_off_even_after_the_admin_withdraws_it(): void
    {
        $this->adminMakesEmailAvailable();
        $page = $this->page()->set('confirmed', true)->call('enable');

        app(Connections::class)->makeUnavailable(Connection::Email);

        Livewire::actingAs($this->owner)->test('pages::households.connections', ['household' => $this->household])
            ->assertSet('state', 'unavailable')
            ->assertSet('switchedOn', true)
            ->call('disable')
            ->assertSet('switchedOn', false);

        $this->assertSame(0, HouseholdConnection::count());
    }

    public function test_when_the_mail_server_changes_the_owner_is_asked_to_confirm_again(): void
    {
        $this->adminMakesEmailAvailable();
        $this->page()->set('confirmed', true)->call('enable');

        $this->mailDelivers('smtp.elsewhere.example');

        $page = Livewire::actingAs($this->owner)->test('pages::households.connections', ['household' => $this->household])
            ->assertSet('state', 'confirm-again')
            ->assertSee('smtp.elsewhere.example');

        $this->assertFalse(app(Connections::class)->allows(Connection::Email, $this->household));

        $page->call('enable')->assertHasErrors('confirmed');

        $page->set('confirmed', true)->call('enable')->assertSet('state', 'on');
        $this->assertTrue(app(Connections::class)->allows(Connection::Email, $this->household));
    }

    public function test_email_says_it_is_paused_while_mail_does_not_deliver(): void
    {
        $this->adminMakesEmailAvailable();
        $this->page()->set('confirmed', true)->call('enable');

        config(['mail.default' => 'log']);

        Livewire::actingAs($this->owner)->test('pages::households.connections', ['household' => $this->household])
            ->assertSet('state', 'paused')
            ->assertSee(__('Email is not delivered right now, so nothing is sent.'));
    }

    public function test_switching_email_on_or_off_is_logged_with_who_and_where_but_never_with_content(): void
    {
        $this->adminMakesEmailAvailable();
        Log::spy();

        $this->page()->set('confirmed', true)->call('enable')->call('disable');

        foreach (['enabled', 'disabled'] as $action) {
            Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $message === 'Household connection changed.'
                && $context === [
                    'household_id' => $this->household->id,
                    'connection' => 'email',
                    'action' => $action,
                    'user_id' => $this->owner->id,
                ])->once();
        }
    }

    public function test_the_household_settings_link_to_connections_for_an_owner_only(): void
    {
        $link = route('households.connections', $this->household);

        Livewire::actingAs($this->owner)->test('pages::households.edit', ['household' => $this->household])->assertSee($link);

        $editor = User::factory()->create();
        app(AddUserToHousehold::class)->handle($this->household, $editor, HouseholdRole::Editor);

        Livewire::actingAs($editor)->test('pages::households.edit', ['household' => $this->household])->assertDontSee($link);
    }
}
