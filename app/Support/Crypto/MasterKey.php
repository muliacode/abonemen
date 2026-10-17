<?php

namespace App\Support\Crypto;

use LogicException;

/**
 * The master key (MK): 32 random bytes that wrap every data key.
 *
 * It never enters the database, the config cache, logs or error pages. The
 * object hides its bytes from var_dump(), print_r() and stack traces, and it
 * cannot be serialised, so it cannot end up in a queued job or a cache entry.
 */
final class MasterKey
{
    public const BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;

    private function __construct(#[\SensitiveParameter] private readonly string $bytes) {}

    /**
     * @throws InvalidMasterKey
     */
    public static function fromBase64(#[\SensitiveParameter] string $encoded): self
    {
        $raw = base64_decode(trim($encoded), true);

        if ($raw === false || strlen($raw) !== self::BYTES) {
            throw new InvalidMasterKey('The master key must be '.self::BYTES.' random bytes, base64-encoded.');
        }

        return new self($raw);
    }

    public static function generate(): self
    {
        return new self(random_bytes(self::BYTES));
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    public function toBase64(): string
    {
        return base64_encode($this->bytes);
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->bytes, $other->bytes);
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['bytes' => '[redacted]'];
    }

    /**
     * @return never
     */
    public function __serialize(): array
    {
        throw new LogicException('The master key cannot be serialised.');
    }

    /**
     * @param  array<mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        throw new LogicException('The master key cannot be unserialised.');
    }
}
