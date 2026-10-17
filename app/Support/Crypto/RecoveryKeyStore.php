<?php

namespace App\Support\Crypto;

use App\Models\Setting;
use LogicException;

/**
 * Keeps the public half of the recovery key in the settings table. The public
 * half is not secret: it can only seal, never unseal.
 */
final class RecoveryKeyStore
{
    public const SETTING = 'crypto.recovery_public_key';

    private ?string $publicKey = null;

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

        Setting::query()->create(['key' => self::SETTING, 'value' => base64_encode($publicKey), 'is_secret' => false]);
        $this->publicKey = $publicKey;
    }

    public function exists(): bool
    {
        return $this->publicKey !== null || Setting::query()->whereKey(self::SETTING)->exists();
    }

    /**
     * @throws RecoveryKeyMissing
     */
    public function publicKey(): string
    {
        if ($this->publicKey !== null) {
            return $this->publicKey;
        }

        $value = Setting::query()->find(self::SETTING)?->value;
        $decoded = $value === null ? false : base64_decode($value, true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new RecoveryKeyMissing('No recovery key is installed, so new data keys cannot be made recoverable.');
        }

        return $this->publicKey = $decoded;
    }

    public function forget(): void
    {
        $this->publicKey = null;
    }
}
