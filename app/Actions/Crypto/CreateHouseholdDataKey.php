<?php

namespace App\Actions\Crypto;

use App\Support\Crypto\DataKeyRing;

/**
 * Gives a new household its first data key. The Accounts domain calls this
 * when it creates a household.
 */
final class CreateHouseholdDataKey
{
    public function __construct(private readonly DataKeyRing $ring) {}

    /**
     * @return int The key version created.
     */
    public function __invoke(string $householdId): int
    {
        return $this->ring->createHousehold($householdId)->version;
    }
}
