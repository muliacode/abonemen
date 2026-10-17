<?php

namespace App\Support\Crypto;

use SodiumException;

/**
 * Wraps a data key with the master key: XChaCha20-Poly1305 (IETF) with a
 * random 24-byte nonce. The associated data names the key's id, owner and
 * version, so a wrapped key only unwraps for the row it was made for.
 *
 * Stored form: "v1:" followed by base64 of nonce || ciphertext || tag. The
 * prefix leaves room for another scheme later without guessing.
 */
final class KeyWrap
{
    private const PREFIX = 'v1:';

    private const NONCE_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

    public static function associatedData(string $keyId, string $ownerType, string $ownerId, int $version): string
    {
        // Length-prefixed, so no two different tuples give the same string.
        return implode('', array_map(
            fn (string $part): string => pack('N', strlen($part)).$part,
            ['abonemen:data-key', $keyId, $ownerType, $ownerId, (string) $version],
        ));
    }

    /**
     * @param  string|null  $nonce  Only for known-answer tests; leave null.
     */
    public static function wrap(
        MasterKey $masterKey,
        #[\SensitiveParameter] string $dataKey,
        string $associatedData,
        ?string $nonce = null,
    ): string {
        $nonce ??= random_bytes(self::NONCE_BYTES);

        $sealed = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($dataKey, $associatedData, $nonce, $masterKey->bytes());

        return self::PREFIX.sodium_bin2base64($nonce.$sealed, SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    /**
     * @throws KeyUnwrapFailed When the wrapped key was altered, belongs to
     *                         another row, or was wrapped by another master key.
     */
    public static function unwrap(MasterKey $masterKey, string $wrapped, string $associatedData): string
    {
        if (! str_starts_with($wrapped, self::PREFIX)) {
            throw new KeyUnwrapFailed('Unknown key wrapping format.');
        }

        try {
            $decoded = sodium_base642bin(substr($wrapped, strlen(self::PREFIX)), SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (SodiumException) {
            throw new KeyUnwrapFailed('The wrapped key is not valid base64.');
        }

        if (strlen($decoded) <= self::NONCE_BYTES + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new KeyUnwrapFailed('The wrapped key is too short.');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($decoded, self::NONCE_BYTES),
            $associatedData,
            substr($decoded, 0, self::NONCE_BYTES),
            $masterKey->bytes(),
        );

        if ($plaintext === false) {
            throw new KeyUnwrapFailed('The wrapped key does not belong to this row, or the master key is wrong.');
        }

        return $plaintext;
    }
}
