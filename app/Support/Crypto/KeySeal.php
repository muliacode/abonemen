<?php

namespace App\Support\Crypto;

use SodiumException;

/**
 * Seals a data key to the public half of the recovery key with a libsodium
 * sealed box (X25519 and XSalsa20-Poly1305), so the server can make every new
 * key recoverable without holding the private half.
 *
 * A sealed box has no associated data, so the sealed message carries the same
 * associated data as the master-key wrap, followed by the key. Unsealing
 * checks it, so a sealed key copied into another row is refused.
 *
 * Stored form: "v1:" followed by base64 of the sealed box.
 */
final class KeySeal
{
    private const PREFIX = 'v1:';

    public static function seal(string $publicKey, #[\SensitiveParameter] string $dataKey, string $associatedData): string
    {
        return self::PREFIX.sodium_bin2base64(
            sodium_crypto_box_seal($associatedData.$dataKey, $publicKey),
            SODIUM_BASE64_VARIANT_ORIGINAL,
        );
    }

    /**
     * @throws KeyUnwrapFailed
     */
    public static function unseal(RecoverySecret $secret, string $sealed, string $associatedData): string
    {
        if (! str_starts_with($sealed, self::PREFIX)) {
            throw new KeyUnwrapFailed('Unknown key sealing format.');
        }

        try {
            $box = sodium_base642bin(substr($sealed, strlen(self::PREFIX)), SODIUM_BASE64_VARIANT_ORIGINAL);
            $message = sodium_crypto_box_seal_open($box, $secret->keyPair());
        } catch (SodiumException) {
            $message = false;
        }

        if ($message === false) {
            throw new KeyUnwrapFailed('The sealed key cannot be opened with this recovery key.');
        }

        $boundTo = substr($message, 0, -DataKeyMaterial::BYTES);
        if (strlen($message) !== strlen($associatedData) + DataKeyMaterial::BYTES || ! hash_equals($associatedData, $boundTo)) {
            throw new KeyUnwrapFailed('The sealed key belongs to another row.');
        }

        return substr($message, -DataKeyMaterial::BYTES);
    }
}
