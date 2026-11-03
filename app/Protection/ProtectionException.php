<?php

namespace App\Protection;

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
        return new self('A protected value could not be read. It was changed or damaged after it was stored.');
    }
}
