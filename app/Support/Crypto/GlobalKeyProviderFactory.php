<?php

namespace App\Support\Crypto;

use ParagonIE\CipherSweet\Contract\KeyProviderInterface;
use ParagonIE\CipherSweet\KeyProvider\StringProvider;

/**
 * The key provider spatie/laravel-ciphersweet uses for account fields
 * (config ciphersweet.providers.custom): it supplies the GDK.
 */
final class GlobalKeyProviderFactory
{
    public function __invoke(): KeyProviderInterface
    {
        return new StringProvider(GlobalDataKey::get()->getRawKey());
    }
}
