<?php

namespace App\Actions\Crypto;

use App\Support\Crypto\DataKeyRing;

/**
 * Creates the instance's global data key, once. The setup wizard (#25) calls
 * this after creating the master key.
 */
final class CreateGlobalDataKey
{
    public function __construct(private readonly DataKeyRing $ring) {}

    /**
     * @return bool False when the key already existed.
     */
    public function __invoke(): bool
    {
        if ($this->ring->hasGlobal()) {
            return false;
        }

        $this->ring->createGlobal();

        return true;
    }
}
