<?php

namespace App\Support\Crypto;

use LogicException;

/**
 * An unwrapped data key: its 32 bytes with the version they belong to. Like
 * MasterKey, it hides its bytes from dumps and cannot be serialised.
 */
final class DataKeyMaterial
{
    public const BYTES = 32;

    public function __construct(
        #[\SensitiveParameter] private readonly string $bytes,
        public readonly int $version,
    ) {
        if (strlen($bytes) !== self::BYTES) {
            throw new LogicException('A data key must be '.self::BYTES.' bytes.');
        }
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    /**
     * @return array<string, int|string>
     */
    public function __debugInfo(): array
    {
        return ['bytes' => '[redacted]', 'version' => $this->version];
    }

    /**
     * @return never
     */
    public function __serialize(): array
    {
        throw new LogicException('A data key cannot be serialised.');
    }

    /**
     * @param  array<mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        throw new LogicException('A data key cannot be unserialised.');
    }
}
