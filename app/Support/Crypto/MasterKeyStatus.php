<?php

namespace App\Support\Crypto;

enum MasterKeyStatus
{
    /** Neither ABONEMEN_MASTER_KEY_FILE nor ABONEMEN_MASTER_KEY is set. */
    case Missing;

    /** A key is configured but cannot be read or has the wrong form. */
    case Invalid;

    case Ready;
}
