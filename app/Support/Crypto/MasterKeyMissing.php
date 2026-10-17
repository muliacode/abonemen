<?php

namespace App\Support\Crypto;

use RuntimeException;

/**
 * Something needs the master key and none is configured.
 */
final class MasterKeyMissing extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No master key is configured: set ABONEMEN_MASTER_KEY_FILE (preferred) or ABONEMEN_MASTER_KEY.');
    }
}
