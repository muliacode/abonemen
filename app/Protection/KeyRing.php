<?php

namespace App\Protection;

use App\Models\Household;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;

/**
 * Holds the keys that protect personal data.
 *
 * One master key lives in the environment. Each Household gets its own random
 * data key, stored in the database wrapped by the master key, and platform-wide
 * data such as sign-in emails is protected with keys derived from the master key.
 */
final class KeyRing
{
    private const CIPHER = 'aes-256-gcm';

    /** @var array<string, string> Unwrapped household keys, by master key fingerprint and wrapped key. */
    private array $unwrapped = [];

    /** @var array<int, string> */
    private array $wrappedByHousehold = [];

    /**
     * Make a new Household data key, returning it and the wrapped form to store in the database.
     *
     * @return array{0: string, 1: string}
     */
    public function newHouseholdKey(): array
    {
        $key = random_bytes(32);

        return [$key, $this->encrypt($key, $this->masterKey())];
    }

    /**
     * Get a Household's data key from the wrapped form stored in the database.
     */
    public function householdKey(string $wrapped): string
    {
        return $this->unwrap($wrapped);
    }

    /**
     * Get the key that protects the data of the Household with the given id.
     */
    public function householdKeyById(int $householdId): string
    {
        $wrapped = $this->wrappedByHousehold[$householdId]
            ??= (string) Household::query()->whereKey($householdId)->value('data_key');

        return $this->unwrap($wrapped);
    }

    /**
     * Get the key that protects platform-wide data, such as sign-in emails.
     */
    public function platformKey(): string
    {
        return $this->derive('abonemen/protected-platform-data');
    }

    /**
     * Get the key used to build blind indexes.
     */
    public function blindIndexKey(): string
    {
        return $this->derive('abonemen/blind-index');
    }

    /**
     * Protect a value with the given key.
     */
    public function seal(string $plaintext, string $key): string
    {
        return $this->encrypt($plaintext, $key);
    }

    /**
     * Read a protected value with the given key.
     */
    public function open(string $ciphertext, string $key): string
    {
        try {
            return (new Encrypter($key, self::CIPHER))->decryptString($ciphertext);
        } catch (DecryptException) {
            throw ProtectionException::unreadableValue();
        }
    }

    private function encrypt(string $plaintext, string $key): string
    {
        return (new Encrypter($key, self::CIPHER))->encryptString($plaintext);
    }

    private function unwrap(string $wrapped): string
    {
        $master = $this->masterKey();

        return $this->unwrapped[hash('sha256', $master).'|'.$wrapped] ??= $this->unwrapWith($master, $wrapped);
    }

    private function unwrapWith(string $master, string $wrapped): string
    {
        try {
            return (new Encrypter($master, self::CIPHER))->decryptString($wrapped);
        } catch (DecryptException) {
            throw ProtectionException::wrongMasterKey();
        }
    }

    private function derive(string $purpose): string
    {
        return hash_hkdf('sha256', $this->masterKey(), 32, $purpose);
    }

    private function masterKey(): string
    {
        $configured = config('protection.master_key');

        if (! is_string($configured) || $configured === '') {
            throw ProtectionException::masterKeyMissing();
        }

        $key = str_starts_with($configured, 'base64:') ? base64_decode(substr($configured, 7), true) : false;

        if ($key === false || strlen($key) !== 32) {
            throw ProtectionException::masterKeyInvalid();
        }

        return $key;
    }
}
