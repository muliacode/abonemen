<?php

namespace App\Support\Crypto;

use ParagonIE\CipherSweet\Contract\BackendInterface;

/**
 * Account fields use the same backend as household fields: BoringCrypto's
 * format, made faster (FastBoringCrypto).
 */
final class GlobalBackendFactory
{
    public function __invoke(): BackendInterface
    {
        return new FastBoringCrypto;
    }
}
