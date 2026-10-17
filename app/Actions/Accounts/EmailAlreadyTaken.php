<?php

namespace App\Actions\Accounts;

use RuntimeException;

/**
 * Another account already has this email address. Emails are encrypted, so
 * the database cannot enforce this; the User model checks before every save.
 */
final class EmailAlreadyTaken extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Another account already uses this email address.');
    }
}
