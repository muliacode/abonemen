<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent from the Email page to prove that the settings work.
 */
class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Test email from Abonemen'));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.test');
    }
}
