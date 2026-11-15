<?php

namespace App\Enums;

/**
 * Where a Connection stands for one Household, which is what an Owner is told.
 */
enum ConnectionState: string
{
    /** The Instance Admin has not made it available. */
    case Unavailable = 'unavailable';

    /** Available, but nobody would receive it, because mail does not deliver right now. */
    case Paused = 'paused';

    /** Available, and the Owner has not switched it on. */
    case Off = 'off';

    /** The Owner switched it on, but the mail server or the disclosure changed since. */
    case ConfirmAgain = 'confirm-again';

    case On = 'on';

    /**
     * Whether an Owner can switch the Connection on now: they have not yet, or must confirm again.
     */
    public function canBeSwitchedOn(): bool
    {
        return $this === self::Off || $this === self::ConfirmAgain;
    }
}
