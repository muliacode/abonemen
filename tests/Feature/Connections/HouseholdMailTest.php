<?php

namespace Tests\Feature\Connections;

use App\Enums\Connection;
use App\Models\Household;
use App\Models\User;
use App\Support\Connections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\Mail\HouseholdNoteMail;
use Tests\TestCase;

/**
 * A Household email is sent only while its Household's email Connection is on, checked when the message is
 * actually sent. These tests send through a real mailer whose transport only collects messages: Mail::fake()
 * would skip the check.
 */
class HouseholdMailTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        // The app sees an SMTP server that delivers; the transport keeps what it is given so that a test can count it.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => ['transport' => 'array', 'host' => 'smtp.home.example'],
        ]);
        Mail::forgetMailers();

        $this->household = Household::factory()->create();
    }

    private function switchEmailOn(): void
    {
        $connections = app(Connections::class);
        $connections->makeAvailable(Connection::Email);
        $connections->confirm(Connection::Email, $this->household, User::factory()->create());
    }

    private function send(): void
    {
        Mail::to('ada@example.com')->send(new HouseholdNoteMail($this->household));
    }

    private function delivered(): int
    {
        return Mail::mailer()->getSymfonyTransport()->messages()->count();
    }

    public function test_nothing_is_sent_while_the_connection_is_off(): void
    {
        $this->send();

        $this->assertSame(0, $this->delivered());
    }

    public function test_the_email_is_sent_while_the_connection_is_on(): void
    {
        $this->switchEmailOn();

        $this->send();

        $this->assertSame(1, $this->delivered());
    }

    public function test_an_email_already_in_the_queue_is_not_sent_once_the_owner_switches_email_off(): void
    {
        $this->switchEmailOn();
        Queue::fake();

        Mail::to('ada@example.com')->queue(new HouseholdNoteMail($this->household));
        app(Connections::class)->disable(Connection::Email, $this->household);

        Queue::pushed(SendQueuedMailable::class)->first()->handle(app('mail.manager'));

        $this->assertSame(0, $this->delivered());
    }
}
