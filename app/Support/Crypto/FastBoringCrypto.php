<?php

namespace App\Support\Crypto;

use Override;
use ParagonIE\CipherSweet\Backend\BoringCrypto;
use ParagonIE\CipherSweet\Backend\Key\SymmetricKey;
use ParagonIE\CipherSweet\Exception\InvalidCiphertextException;
use SodiumException;
use WeakMap;

/**
 * SPIKE (#12): CipherSweet's BoringCrypto (XChaCha20 with a BLAKE2b MAC) with
 * the same wire format, made faster for decrypting many fields:
 *
 * - libsodium's constant-time base64 instead of the pure-PHP decoder;
 * - the encryption and MAC subkeys are derived once per field key, not on
 *   every call.
 *
 * Ciphertexts are interchangeable with BoringCrypto in both directions.
 */
final class FastBoringCrypto extends BoringCrypto
{
    /** @var WeakMap<SymmetricKey, array{0: string, 1: string}> */
    private WeakMap $subkeys;

    public function __construct()
    {
        $this->subkeys = new WeakMap;
    }

    #[Override]
    public function encrypt(#[\SensitiveParameter] string $plaintext, #[\SensitiveParameter] SymmetricKey $key, #[\SensitiveParameter] string $aad = ''): string
    {
        [$encryptionKey, $integrityKey] = $this->subkeysFor($key);

        $nonce = random_bytes(self::NONCE_SIZE);
        $ciphertext = sodium_crypto_stream_xchacha20_xor($plaintext, $nonce, $encryptionKey);
        $mac = $this->mac($nonce, $aad, $ciphertext, $integrityKey);

        return self::MAGIC_HEADER.sodium_bin2base64($nonce.$mac.$ciphertext, SODIUM_BASE64_VARIANT_URLSAFE);
    }

    #[Override]
    public function decrypt(#[\SensitiveParameter] string $ciphertext, #[\SensitiveParameter] SymmetricKey $key, #[\SensitiveParameter] string $aad = ''): string
    {
        if (! hash_equals(self::MAGIC_HEADER, substr($ciphertext, 0, 5))) {
            throw new InvalidCiphertextException('Invalid ciphertext header.');
        }

        try {
            $decoded = sodium_base642bin(substr($ciphertext, 5), SODIUM_BASE64_VARIANT_URLSAFE);
        } catch (SodiumException) {
            throw new InvalidCiphertextException('Invalid ciphertext encoding.');
        }
        if (strlen($decoded) < self::NONCE_SIZE + self::MAC_SIZE) {
            throw new InvalidCiphertextException('Message is too short.');
        }

        [$encryptionKey, $integrityKey] = $this->subkeysFor($key);
        $nonce = substr($decoded, 0, self::NONCE_SIZE);
        $storedMac = substr($decoded, self::NONCE_SIZE, self::MAC_SIZE);
        $encrypted = substr($decoded, self::NONCE_SIZE + self::MAC_SIZE);

        if (! hash_equals($this->mac($nonce, $aad, $encrypted, $integrityKey), $storedMac)) {
            throw new SodiumException('Invalid ciphertext');
        }

        return sodium_crypto_stream_xchacha20_xor($encrypted, $nonce, $encryptionKey);
    }

    /**
     * BoringCrypto's MAC input, byte for byte: header, nonce, then the lengths
     * of the AAD and the ciphertext as decimal strings, each before its value.
     */
    private function mac(string $nonce, string $aad, string $ciphertext, string $integrityKey): string
    {
        return sodium_crypto_generichash(
            self::MAGIC_HEADER.$nonce.strlen($aad).$aad.strlen($ciphertext).$ciphertext,
            $integrityKey,
            self::MAC_SIZE,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function subkeysFor(SymmetricKey $key): array
    {
        return $this->subkeys[$key] ??= [
            $this->getEncryptionKey($key)->getRawKey(),
            $this->getIntegrityKey($key)->getRawKey(),
        ];
    }
}
