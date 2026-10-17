<?php

namespace App\Support\Crypto;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\EncryptException;
use ParagonIE\CipherSweet\Backend\Key\SymmetricKey;
use ParagonIE\CipherSweet\Util;
use Throwable;

/**
 * A Laravel encrypter on the global data key, for packages that encrypt their
 * own columns: Fortify's TOTP secret and recovery codes go through
 * Fortify::encryptUsing(new GlobalEncrypter('fortify-two-factor')) once
 * Fortify is installed (#31). Each purpose gets its own key, derived from the
 * GDK, and the purpose is the associated data.
 */
final class GlobalEncrypter implements Encrypter
{
    private readonly FastBoringCrypto $backend;

    public function __construct(private readonly string $purpose)
    {
        $this->backend = new FastBoringCrypto;
    }

    public function encrypt(#[\SensitiveParameter] $value, $serialize = true): string
    {
        try {
            return $this->backend->encrypt($serialize ? serialize($value) : (string) $value, $this->key(), $this->purpose);
        } catch (Throwable $e) {
            throw new EncryptException('Could not encrypt the data.', previous: $e);
        }
    }

    public function decrypt($payload, $unserialize = true): mixed
    {
        try {
            $plaintext = $this->backend->decrypt((string) $payload, $this->key(), $this->purpose);
        } catch (Throwable $e) {
            throw new DecryptException('The payload is invalid.', previous: $e);
        }

        return $unserialize ? unserialize($plaintext, ['allowed_classes' => false]) : $plaintext;
    }

    public function getKey(): string
    {
        return $this->key()->getRawKey();
    }

    /**
     * @return list<string>
     */
    public function getAllKeys(): array
    {
        return [$this->getKey()];
    }

    /**
     * @return list<string>
     */
    public function getPreviousKeys(): array
    {
        return [];
    }

    private function key(): SymmetricKey
    {
        return new SymmetricKey(Util::HKDF(
            new SymmetricKey(app(DataKeyRing::class)->global()->bytes()),
            'abonemen',
            'encrypter:'.$this->purpose,
        ));
    }
}
