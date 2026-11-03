<?php

namespace Tests\Feature\Localisation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecipientLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_email_is_written_in_the_recipients_language_not_the_current_one(): void
    {
        $recipient = User::factory()->unverified()->create(['locale' => 'nl']);

        // Someone else, working in English, triggers the email.
        App::setLocale('en');

        $recipient->sendEmailVerificationNotification();

        $this->assertSame('Verifieer je e-mailadres', $this->lastEmailSubject());
        $this->assertSame('en', App::getLocale(), 'The current request must keep its own language.');
    }

    public function test_each_recipient_gets_their_own_language(): void
    {
        $dutch = User::factory()->unverified()->create(['locale' => 'nl']);
        $french = User::factory()->unverified()->create(['locale' => 'fr']);

        $dutch->sendEmailVerificationNotification();
        $this->assertSame('Verifieer je e-mailadres', $this->lastEmailSubject());

        $french->sendEmailVerificationNotification();
        $this->assertSame('Vérifier votre adresse e-mail', $this->lastEmailSubject());
    }

    public function test_a_recipient_without_a_chosen_language_gets_the_current_one(): void
    {
        $recipient = User::factory()->unverified()->create();

        App::setLocale('de');

        $recipient->sendEmailVerificationNotification();

        $this->assertSame('Bestätigen Sie Ihre E-Mail-Adresse', $this->lastEmailSubject());
    }

    public function test_any_content_can_be_made_in_a_users_language(): void
    {
        $user = User::factory()->create(['locale' => 'id']);

        $this->assertSame('id', $user->preferredLocale());
        $this->assertSame('Rumah Tangga', $user->inPreferredLocale(fn () => __('Households')));
    }

    private function lastEmailSubject(): string
    {
        return Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage()->getSubject();
    }
}
