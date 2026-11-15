<?php

namespace Tests\Fixtures\Mail;

use App\Mail\HouseholdMail;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A stand-in for the Household emails later issues add (Invites, Reminders).
 */
class HouseholdNoteMail extends HouseholdMail
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'A note for the Household');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Netflix renews tomorrow.</p>');
    }
}
