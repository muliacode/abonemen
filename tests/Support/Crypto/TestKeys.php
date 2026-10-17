<?php

namespace Tests\Support\Crypto;

use App\Support\Crypto\DataKeyRing;
use App\Support\Crypto\RecoveryKeyStore;
use App\Support\Crypto\RecoverySecret;

/**
 * Sets up the key hierarchy for a test, the way the setup wizard will: the
 * master key comes from phpunit.xml, the recovery key is installed here.
 */
final class TestKeys
{
    /**
     * Installs a fresh recovery key and returns its private half.
     */
    public static function installRecoveryKey(): RecoverySecret
    {
        $secret = RecoverySecret::generate();
        app(RecoveryKeyStore::class)->install($secret->publicKey());

        return $secret;
    }

    /**
     * Drops every unwrapped key and cached public key, as a new request would.
     */
    public static function forget(): void
    {
        app(DataKeyRing::class)->forget();
        app(RecoveryKeyStore::class)->forget();
    }
}
