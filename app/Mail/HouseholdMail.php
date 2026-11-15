<?php

namespace App\Mail;

use App\Enums\Connection;
use App\Models\Household;
use App\Support\Connections;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailable;

/**
 * Every email that carries a Household's data extends this. It is sent only while the Household's email
 * Connection is on, and that is checked when the message is actually sent, not when it is queued, so withdrawing
 * consent stops mail already waiting. A closed gate skips the email quietly: no retry, no failed job.
 *
 * Emails that carry no Household data (password reset, verification, the admin's test email) do not extend
 * this; an architecture test keeps that list explicit (ADR 0007).
 */
abstract class HouseholdMail extends Mailable
{
    public function __construct(public readonly Household $household)
    {
        //
    }

    /**
     * @param  Factory|Mailer  $mailer
     */
    public function send($mailer)
    {
        if (! app(Connections::class)->allows(Connection::Email, $this->household)) {
            return null;
        }

        return parent::send($mailer);
    }
}
