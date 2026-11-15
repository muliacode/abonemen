<?php

namespace App\Enums;

/**
 * An outbound call the platform can make, each off until it is switched on. See the glossary.
 */
enum Connection: string
{
    case Email = 'email';

    /**
     * The version of what the Disclosure says is sent. Raise it when the data or the recipients change, never for
     * wording, so that Owners confirm again exactly when their earlier consent no longer covers what is sent.
     */
    public function disclosureVersion(): int
    {
        return match ($this) {
            self::Email => 1,
        };
    }
}
