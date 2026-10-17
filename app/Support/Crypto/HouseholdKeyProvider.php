<?php

namespace App\Support\Crypto;

use Override;
use ParagonIE\CipherSweet\Backend\Key\SymmetricKey;
use ParagonIE\CipherSweet\Contract\KeyProviderInterface;
use ParagonIE\CipherSweet\KeyProvider\MultiTenantProvider;
use RuntimeException;

/**
 * CipherSweet's multi-tenant provider with one tenant per household key
 * version, named "{household id}@{version}". Tenants are unwrapped from the
 * data key ring on first use instead of up front.
 */
final class HouseholdKeyProvider extends MultiTenantProvider
{
    public function __construct(private readonly DataKeyRing $ring)
    {
        parent::__construct([]);
    }

    public static function tenant(string $householdId, int $version): string
    {
        return "{$householdId}@{$version}";
    }

    #[Override]
    public function getTenant(string|int $name): KeyProviderInterface
    {
        [$householdId, $version] = explode('@', (string) $name, 2) + [1 => ''];
        if ($householdId === '' || ! ctype_digit($version)) {
            throw new RuntimeException("Not a household key tenant: {$name}.");
        }

        $key = new SymmetricKey($this->ring->household($householdId, (int) $version)->bytes());

        return new class($key) implements KeyProviderInterface
        {
            public function __construct(private readonly SymmetricKey $key) {}

            public function getSymmetricKey(): SymmetricKey
            {
                return $this->key;
            }
        };
    }

    #[Override]
    public function getActiveTenant(): KeyProviderInterface
    {
        return $this->getTenant($this->active ?? throw new RuntimeException('No active household key.'));
    }
}
