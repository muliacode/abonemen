<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a Connection is asked to do something it must not.
 */
final class ConnectionException extends RuntimeException
{
    public static function notAvailable(): self
    {
        return new self('The Instance Admin has not made this connection available, so a Household cannot switch it on.');
    }

    public static function mailDoesNotDeliver(): self
    {
        return new self('Email cannot be made available while the mailer only writes emails to a log: nobody would receive them. Set up email first.');
    }
}
