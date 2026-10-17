<?php

namespace App\Support\Crypto;

use ParagonIE\CipherSweet\AAD;
use ParagonIE\CipherSweet\Backend\Key\SymmetricKey;
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\Exception\InvalidCiphertextException;
use SodiumException;

/**
 * Encrypts household fields with CipherSweet's multi-tenant mode: a version of
 * the household data key is the tenant root key, CipherSweet derives one key
 * per table and column from it, and the row's ULID and household are the
 * associated data, so a ciphertext only decrypts in the row it was written for.
 */
final class HouseholdCipher
{
    private readonly CipherSweet $engine;

    /** @var array<string, SymmetricKey> Field keys by household, version, table and column. */
    private array $fieldKeys = [];

    public function __construct(DataKeyRing $ring)
    {
        $this->engine = new CipherSweet(new HouseholdKeyProvider($ring), new FastBoringCrypto);
    }

    public function encrypt(#[\SensitiveParameter] string $plaintext, FieldContext $field): string
    {
        return $this->engine->getBackend()->encrypt($plaintext, $this->fieldKey($field), $this->associatedData($field));
    }

    /**
     * @throws FieldDecryptionFailed When the ciphertext was altered, moved to
     *                               another row or household, or written with
     *                               another key.
     */
    public function decrypt(string $ciphertext, FieldContext $field): string
    {
        try {
            return $this->engine->getBackend()->decrypt($ciphertext, $this->fieldKey($field), $this->associatedData($field));
        } catch (InvalidCiphertextException|SodiumException $e) {
            throw new FieldDecryptionFailed("{$field->table}.{$field->column} of row {$field->rowId} cannot be decrypted.", previous: $e);
        }
    }

    /**
     * Drops cached field keys, as a new request would.
     */
    public function forget(): void
    {
        $this->fieldKeys = [];
    }

    private function fieldKey(FieldContext $field): SymmetricKey
    {
        $tenant = HouseholdKeyProvider::tenant($field->householdId, $field->keyVersion);
        $cacheKey = "{$tenant}\0{$field->table}\0{$field->column}";

        if (! isset($this->fieldKeys[$cacheKey])) {
            $this->engine->setActiveTenant($tenant);
            $this->fieldKeys[$cacheKey] = $this->engine->getFieldSymmetricKey($field->table, $field->column);
        }

        return $this->fieldKeys[$cacheKey];
    }

    private function associatedData(FieldContext $field): string
    {
        return (new AAD([], ["row:{$field->rowId}", "household:{$field->householdId}"]))->canonicalize();
    }
}
