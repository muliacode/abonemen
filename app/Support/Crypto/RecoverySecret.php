<?php

namespace App\Support\Crypto;

use LogicException;

/**
 * The private half of the recovery key (RK): an X25519 secret key that only the
 * admin holds. The server never stores it.
 *
 * Shown once, at setup, as 14 groups of 4 base32 characters: the 32-byte key
 * followed by a 3-byte BLAKE2b checksum, so a typo is caught before anything
 * is unsealed. Reading is forgiving: case, spaces and dashes do not matter, and
 * 0, 1 and 8 are read as O, I and B.
 */
final class RecoverySecret
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const CHECKSUM_BYTES = 3;

    private function __construct(#[\SensitiveParameter] private readonly string $secretKey) {}

    /**
     * Makes a new recovery key pair.
     */
    public static function generate(): self
    {
        return new self(sodium_crypto_box_secretkey(sodium_crypto_box_keypair()));
    }

    /**
     * @throws InvalidRecoverySecret
     */
    public static function fromDisplay(#[\SensitiveParameter] string $typed): self
    {
        $normalised = strtr(strtoupper((string) preg_replace('/[\s\-]+/', '', $typed)), '018', 'OIB');
        $bytes = self::base32Decode($normalised);

        if ($bytes === null || strlen($bytes) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES + self::CHECKSUM_BYTES) {
            throw new InvalidRecoverySecret('A recovery key is 56 letters and digits, in 14 groups of 4.');
        }

        $secretKey = substr($bytes, 0, SODIUM_CRYPTO_BOX_SECRETKEYBYTES);
        if (! hash_equals(self::checksum($secretKey), substr($bytes, SODIUM_CRYPTO_BOX_SECRETKEYBYTES))) {
            throw new InvalidRecoverySecret('This recovery key does not check out; look for a typo.');
        }

        return new self($secretKey);
    }

    public function toDisplay(): string
    {
        $encoded = self::base32Encode($this->secretKey.self::checksum($this->secretKey));

        return implode('-', str_split($encoded, 4));
    }

    public function publicKey(): string
    {
        return sodium_crypto_box_publickey_from_secretkey($this->secretKey);
    }

    public function keyPair(): string
    {
        return sodium_crypto_box_keypair_from_secretkey_and_publickey($this->secretKey, $this->publicKey());
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['secretKey' => '[redacted]'];
    }

    /**
     * @return never
     */
    public function __serialize(): array
    {
        throw new LogicException('The recovery key cannot be serialised.');
    }

    /**
     * @param  array<mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        throw new LogicException('The recovery key cannot be unserialised.');
    }

    private static function checksum(string $secretKey): string
    {
        return substr(sodium_crypto_generichash('abonemen:recovery-key'.$secretKey, '', 16), 0, self::CHECKSUM_BYTES);
    }

    /**
     * RFC 4648 base32 without padding.
     */
    private static function base32Encode(string $bytes): string
    {
        $encoded = '';
        $buffer = 0;
        $bits = 0;

        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 31];
            }
            $buffer &= (1 << $bits) - 1;
        }

        if ($bits > 0) {
            $encoded .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $encoded;
    }

    private static function base32Decode(string $encoded): ?string
    {
        $bytes = '';
        $buffer = 0;
        $bits = 0;

        foreach (str_split($encoded) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                return null;
            }
            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $bytes .= chr(($buffer >> $bits) & 255);
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $bytes;
    }
}
