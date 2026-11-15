<?php

namespace Tests\Feature\Connections;

use App\Enums\Connection;
use App\Exceptions\ConnectionException;
use App\Models\Household;
use App\Models\HouseholdConnection;
use App\Models\User;
use App\Support\Connections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Email that carries Household data needs two switches: the Instance Admin makes it available, then an Owner
 * turns it on for the Household after reading what it sends. See ADR 0007.
 */
class ConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_off_for_a_household_on_a_fresh_instance(): void
    {
        $household = Household::factory()->create();

        $this->assertFalse(app(Connections::class)->allows(Connection::Email, $household));
    }

    public function test_email_is_on_once_mail_delivers_and_both_switches_are_on(): void
    {
        $this->mailDeliversThrough('smtp.home.example');
        $household = Household::factory()->create();
        $connections = app(Connections::class);

        $connections->makeAvailable(Connection::Email);
        $connections->confirm(Connection::Email, $household, User::factory()->create());

        $this->assertTrue($connections->allows(Connection::Email, $household));
    }

    public function test_email_pauses_while_mail_does_not_deliver_and_resumes_when_it_does(): void
    {
        $this->mailDeliversThrough('smtp.home.example');
        $household = Household::factory()->create();
        $connections = app(Connections::class);
        $connections->makeAvailable(Connection::Email);
        $connections->confirm(Connection::Email, $household, User::factory()->create());

        config(['mail.default' => 'log']);
        $this->assertFalse($connections->allows(Connection::Email, $household));

        $this->mailDeliversThrough('smtp.home.example');
        $this->assertTrue($connections->allows(Connection::Email, $household));
    }

    public function test_a_changed_mail_server_pauses_email_until_the_owner_confirms_again(): void
    {
        $this->mailDeliversThrough('smtp.home.example');
        $household = Household::factory()->create();
        $owner = User::factory()->create();
        $connections = app(Connections::class);
        $connections->makeAvailable(Connection::Email);
        $connections->confirm(Connection::Email, $household, $owner);

        $this->mailDeliversThrough('smtp.elsewhere.example');
        $this->assertFalse($connections->allows(Connection::Email, $household));

        $connections->confirm(Connection::Email, $household, $owner);
        $this->assertTrue($connections->allows(Connection::Email, $household));
    }

    public function test_a_changed_disclosure_pauses_email_until_the_owner_confirms_again(): void
    {
        $this->mailDeliversThrough('smtp.home.example');
        $household = Household::factory()->create();
        $connections = app(Connections::class);
        $connections->makeAvailable(Connection::Email);
        $connections->confirm(Connection::Email, $household, User::factory()->create());

        // What the Owner confirmed was an older version of the disclosure than the one in this release.
        HouseholdConnection::query()->update(['confirmed_version' => Connection::Email->disclosureVersion() - 1]);

        $this->assertFalse($connections->allows(Connection::Email, $household));
    }

    public function test_the_admin_cannot_make_email_available_while_mail_does_not_deliver(): void
    {
        $connections = app(Connections::class);

        $this->assertFalse($connections->canBeMadeAvailable(Connection::Email));

        try {
            $connections->makeAvailable(Connection::Email);
            $this->fail('Email was made available although nobody would receive it.');
        } catch (ConnectionException) {
            $this->assertFalse($connections->isAvailable(Connection::Email));
        }
    }

    public function test_turning_the_platform_switch_off_pauses_email_and_turning_it_on_resumes_it(): void
    {
        $this->mailDeliversThrough('smtp.home.example');
        $household = Household::factory()->create();
        $connections = app(Connections::class);
        $connections->makeAvailable(Connection::Email);
        $connections->confirm(Connection::Email, $household, User::factory()->create());

        $connections->makeUnavailable(Connection::Email);
        $this->assertFalse($connections->allows(Connection::Email, $household));

        $connections->makeAvailable(Connection::Email);
        $this->assertTrue($connections->allows(Connection::Email, $household));
    }

    public function test_an_owner_can_switch_email_off_even_while_the_platform_switch_is_off(): void
    {
        $this->mailDeliversThrough('smtp.home.example');
        $household = Household::factory()->create();
        $connections = app(Connections::class);
        $connections->makeAvailable(Connection::Email);
        $connections->confirm(Connection::Email, $household, User::factory()->create());
        $connections->makeUnavailable(Connection::Email);

        $connections->disable(Connection::Email, $household);

        $connections->makeAvailable(Connection::Email);
        $this->assertFalse($connections->allows(Connection::Email, $household));
    }

    public function test_an_owner_cannot_switch_email_on_while_the_admin_has_not_made_it_available(): void
    {
        $this->mailDeliversThrough('smtp.home.example');
        $household = Household::factory()->create();

        try {
            app(Connections::class)->confirm(Connection::Email, $household, User::factory()->create());
            $this->fail('Email was switched on for a Household although the Instance Admin has not made it available.');
        } catch (ConnectionException) {
            $this->assertSame(0, HouseholdConnection::count());
        }
    }

    public function test_an_owner_cannot_switch_email_on_while_mail_does_not_deliver(): void
    {
        $this->mailDeliversThrough('smtp.home.example');
        $household = Household::factory()->create();
        $connections = app(Connections::class);
        $connections->makeAvailable(Connection::Email);

        config(['mail.default' => 'log']);

        try {
            $connections->confirm(Connection::Email, $household, User::factory()->create());
            $this->fail('Email was switched on for a Household although nobody would receive it.');
        } catch (ConnectionException) {
            $this->assertSame(0, HouseholdConnection::count());
        }
    }

    public function test_households_are_counted_as_on_or_waiting_to_confirm_again(): void
    {
        $this->mailDeliversThrough('smtp.home.example');
        $connections = app(Connections::class);
        $owner = User::factory()->create();
        [$confirmed, $outdated] = Household::factory()->count(2)->create();
        Household::factory()->create();
        $connections->makeAvailable(Connection::Email);

        $connections->confirm(Connection::Email, $confirmed, $owner);
        $connections->confirm(Connection::Email, $outdated, $owner);
        HouseholdConnection::where('household_id', $outdated->id)->update(['confirmed_version' => 0]);

        $this->assertSame(['on' => 1, 'waiting' => 1], $connections->householdCounts(Connection::Email));
    }

    private function mailDeliversThrough(string $host): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => $host]);
    }

    public function test_households_do_not_have_to_confirm_again_just_because_mail_stopped_delivering(): void
    {
        $this->mailDeliversThrough('smtp.home.example');
        $connections = app(Connections::class);
        $connections->makeAvailable(Connection::Email);
        $connections->confirm(Connection::Email, Household::factory()->create(), User::factory()->create());

        // With no real mailer there is no server to compare with. Their consent has not changed, and holds again
        // as soon as the same server returns.
        config(['mail.default' => 'log']);

        $this->assertSame(['on' => 1, 'waiting' => 0], $connections->householdCounts(Connection::Email));
    }
}
