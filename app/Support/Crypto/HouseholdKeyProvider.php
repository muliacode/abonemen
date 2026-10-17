<?php

namespace App\Support\Crypto;

use Override;
use ParagonIE\CipherSweet\Contract\KeyProviderInterface;
use ParagonIE\CipherSweet\KeyProvider\MultiTenantProvider;
use ParagonIE\CipherSweet\KeyProvider\StringProvider;

/**
 * CipherSweet's multi-tenant provider with one tenant per household. Tenants
 * are loaded from the key ring on first use instead of up front.
 */
final class HouseholdKeyProvider extends MultiTenantProvider
{
    public function __construct(private readonly HouseholdKeyRing $ring)
    {
        parent::__construct([]);
    }

    #[Override]
    public function getTenant(string|int $name): KeyProviderInterface
    {
        return new StringProvider($this->ring->get((string) $name)->getRawKey());
    }

    #[Override]
    public function getActiveTenant(): KeyProviderInterface
    {
        return $this->getTenant($this->active ?? throw new \RuntimeException('No active household.'));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    #[Override]
    public function getTenantFromRow(array $row, string $tableName): string
    {
        return (string) ($row['household_id'] ?? throw new \RuntimeException("Row of {$tableName} has no household_id."));
    }
}
