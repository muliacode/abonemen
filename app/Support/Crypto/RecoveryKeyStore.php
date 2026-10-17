<?php

namespace App\Support\Crypto;

use App\Models\Setting;
use LogicException;

/**
 * Keeps the public half of the recovery key in the settings table. The public
 * half is not secret: it can only seal, never unseal.
 *
 * It is authenticated with the master key, though. Every new data key is sealed
 * to it, so someone who could swap it for a key of their own, for example
 * through an SQL injection that writes, could unseal every data key made after
 * that. The stored value carries a MAC under a key derived from the master key
 * (MasterKey::recoveryPublicKeyMacKey), and a public key that fails it is
 * refused. Recovering under a new master key (#140) must store the public key
 * again under that key.
 *
 * Stored form: "v1:" base64(public key) "." base64(HMAC-SHA512-256 tag).
 */
final class RecoveryKeyStore
{
    public const SETTING = 'crypto.recovery_public_key';

    private const PREFIX = 'v1:';

    private ?string $publicKey = null;

    public function __construct(private readonly MasterKeyLoader $masterKey) {}

    /**
     * Stores the public half of a new recovery key. Replacing it is a key
     * rotation (#140), which must also reseal every data key, so it is refused
     * here.
     */
    public function install(string $publicKey): void
    {
        if (strlen($publicKey) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new LogicException('A recovery public key must be '.SODIUM_CRYPTO_BOX_PUBLICKEYBYTES.' bytes.');
        }

        if ($this->exists()) {
            throw new LogicException('A recovery key is already installed.');
        }

        Setting::query()->create(['key' => self::SETTING, 'value' => $this->encode($publicKey), 'is_secret' => false]);
        $this->publicKey = $publicKey;
    }

    public function exists(): bool
    {
        return $this->publicKey !== null || Setting::query()->whereKey(self::SETTING)->exists();
    }

    /**
     * @throws RecoveryKeyMissing When none is installed.
     * @throws RecoveryKeyTampered When the stored key was not written with this master key.
     */
    public function publicKey(): string
    {
        if ($this->publicKey !== null) {
            return $this->publicKey;
        }

        $value = Setting::query()->find(self::SETTING)?->value;
        if ($value === null) {
            throw new RecoveryKeyMissing('No recovery key is installed, so new data keys cannot be made recoverable.');
        }

        return $this->publicKey = $this->decode($value);
    }

    public function forget(): void
    {
        $this->publicKey = null;
    }

    private function encode(string $publicKey): string
    {
        return self::PREFIX.base64_encode($publicKey).'.'.base64_encode(sodium_crypto_auth($publicKey, $this->macKey()));
    }

    private function decode(string $value): string
    {
        $parts = str_starts_with($value, self::PREFIX) ? explode('.', substr($value, strlen(self::PREFIX)), 2) : [];
        $publicKey = base64_decode($parts[0] ?? '', true);
        $tag = base64_decode($parts[1] ?? '', true);

        if ($publicKey === false || $tag === false
            || strlen($publicKey) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES
            || strlen($tag) !== SODIUM_CRYPTO_AUTH_BYTES
            || ! sodium_crypto_auth_verify($tag, $publicKey, $this->macKey())) {
            throw new RecoveryKeyTampered('The stored recovery public key was not written with this master key, so no data key is sealed to it.');
        }

        return $publicKey;
    }

    private function macKey(): string
    {
        return $this->masterKey->key()->recoveryPublicKeyMacKey();
    }
}
