<?php

namespace App\Support\Crypto;

use ParagonIE\CipherSweet\AAD;
use ParagonIE\CipherSweet\Backend\BoringCrypto;
use ParagonIE\CipherSweet\Backend\Key\SymmetricKey;
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\Contract\BackendInterface;

/**
 * Encrypts household fields with CipherSweet's multi-tenant mode: the HDK of
 * the row's household is the tenant root key, CipherSweet derives one key per
 * table and column, and the row's ULID and household id are the associated
 * data, so a ciphertext only decrypts in the row it was written for.
 */
final class HouseholdCipher
{
    private readonly CipherSweet $engine;

    /** @var array<string, SymmetricKey> Derived field keys, by household, table and column. */
    private array $fieldKeys = [];

    public function __construct(HouseholdKeyRing $ring, ?BackendInterface $backend = null)
    {
        $this->engine = new CipherSweet(new HouseholdKeyProvider($ring), $backend ?? new FastBoringCrypto);
    }

    /**
     * SPIKE (#12): a cipher on CipherSweet's own BoringCrypto backend instead, for the benchmark.
     */
    public static function withStockBackend(HouseholdKeyRing $ring): self
    {
        return new self($ring, new BoringCrypto);
    }

    public function encrypt(string $plaintext, string $householdId, string $rowId, string $table, string $column): string
    {
        return $this->engine->getBackend()->encrypt(
            $plaintext,
            $this->fieldKey($householdId, $table, $column),
            $this->aad($householdId, $rowId),
        );
    }

    public function decrypt(string $ciphertext, string $householdId, string $rowId, string $table, string $column): string
    {
        return $this->engine->getBackend()->decrypt(
            $ciphertext,
            $this->fieldKey($householdId, $table, $column),
            $this->aad($householdId, $rowId),
        );
    }

    /**
     * Drops cached field keys, for example after a household key rotation.
     */
    public function flush(?string $householdId = null): void
    {
        if ($householdId === null) {
            $this->fieldKeys = [];

            return;
        }

        $this->fieldKeys = array_filter(
            $this->fieldKeys,
            fn (string $key): bool => ! str_starts_with($key, "{$householdId}\0"),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function fieldKey(string $householdId, string $table, string $column): SymmetricKey
    {
        $cacheKey = "{$householdId}\0{$table}\0{$column}";

        if (! isset($this->fieldKeys[$cacheKey])) {
            $this->engine->setActiveTenant($householdId);
            $this->fieldKeys[$cacheKey] = $this->engine->getFieldSymmetricKey($table, $column);
        }

        return $this->fieldKeys[$cacheKey];
    }

    private function aad(string $householdId, string $rowId): string
    {
        return (new AAD([], ["row:{$rowId}", "household:{$householdId}"]))->canonicalize();
    }
}
