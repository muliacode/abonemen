<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when protected data cannot be read or written safely.
 *
 * Protected data is never returned as garbage: it is either the real value or one of these.
 */
final class ProtectionException extends RuntimeException
{
    public static function masterKeyMissing(): self
    {
        return new self('The master key is not set. Set ABONEMEN_MASTER_KEY in the environment; "php artisan master-key:generate" makes one.');
    }

    public static function masterKeyInvalid(): self
    {
        return new self('The master key is not valid. It must be 32 random bytes written as "base64:...", as "php artisan master-key:generate" makes them.');
    }

    public static function wrongMasterKey(): self
    {
        return new self('The master key does not match the one this data was protected with. Restore the original ABONEMEN_MASTER_KEY or the recovery key.');
    }

    public static function unreadableValue(): self
    {
        return new self('A protected value could not be read. It was changed or damaged after it was stored, or it was protected with a different key (the master key may be the wrong one).');
    }

    public static function householdKeyNotLoaded(): self
    {
        return new self('The Household\'s data key was not loaded with this query, so its protected values cannot be read. Load the whole Household, or include data_key when selecting columns.');
    }

    public static function unknownHousehold(int $householdId): self
    {
        return new self("There is no Household with id {$householdId} to take a data key from.");
    }
}
