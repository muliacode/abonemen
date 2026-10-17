<?php

namespace App\Actions\Crypto;

use App\Support\Crypto\RecoveryKeyStore;
use App\Support\Crypto\RecoverySecret;

/**
 * Makes the recovery key at setup: stores the public half and returns the
 * private half, which the setup wizard (#25) shows once and never stores.
 */
final class CreateRecoveryKey
{
    public function __construct(private readonly RecoveryKeyStore $store) {}

    /**
     * @return string The private half, for display only.
     */
    public function __invoke(): string
    {
        $secret = RecoverySecret::generate();
        $this->store->install($secret->publicKey());

        return $secret->toDisplay();
    }
}
