<?php

namespace App\Support\Crypto;

use ParagonIE\CipherSweet\Backend\Key\SymmetricKey;
use ParagonIE\CipherSweet\Contract\KeyProviderInterface;

/**
 * The key provider spatie/laravel-ciphersweet uses for account fields: the
 * global data key, unwrapped from the key ring only when a field is first
 * encrypted or decrypted.
 */
final class GlobalKeyProviderFactory
{
    public function __invoke(): KeyProviderInterface
    {
        return new class implements KeyProviderInterface
        {
            public function getSymmetricKey(): SymmetricKey
            {
                return new SymmetricKey(app(DataKeyRing::class)->global()->bytes());
            }
        };
    }
}
