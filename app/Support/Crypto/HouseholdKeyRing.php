<?php

namespace App\Support\Crypto;

use ParagonIE\CipherSweet\Backend\Key\SymmetricKey;
use RuntimeException;

/**
 * Household data keys (HDKs) by household id.
 *
 * SPIKE: keys live in memory and are added by the caller. The real ring
 * unwraps versioned keys from the data_keys table with the master key (#19).
 */
final class HouseholdKeyRing
{
    /** @var array<string, SymmetricKey> */
    private array $keys = [];

    public function add(string $householdId, string $rawKey): void
    {
        $this->keys[$householdId] = new SymmetricKey($rawKey);
    }

    public function forget(string $householdId): void
    {
        unset($this->keys[$householdId]);
    }

    public function get(string $householdId): SymmetricKey
    {
        return $this->keys[$householdId]
            ?? throw new RuntimeException("No data key for household {$householdId}.");
    }
}
