<?php

namespace App\Support\Crypto;

use ParagonIE\CipherSweet\Backend\Key\SymmetricKey;
use RuntimeException;

/**
 * The global data key (GDK) that protects account-level fields.
 *
 * SPIKE: the key is read from ABONEMEN_SPIKE_GDK (base64, 32 bytes). The real
 * key is unwrapped from the data_keys table with the master key (#19).
 */
final class GlobalDataKey
{
    private static ?SymmetricKey $key = null;

    public static function get(): SymmetricKey
    {
        if (self::$key !== null) {
            return self::$key;
        }

        $encoded = config('ciphersweet.spike_gdk');
        $raw = is_string($encoded) ? base64_decode($encoded, true) : false;

        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('ABONEMEN_SPIKE_GDK must be 32 bytes, base64-encoded.');
        }

        return self::$key = new SymmetricKey($raw);
    }

    public static function forget(): void
    {
        self::$key = null;
    }
}
