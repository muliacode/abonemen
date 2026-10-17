<?php

namespace App\Support\Crypto;

use RuntimeException;

/**
 * The stored public half of the recovery key fails its master-key MAC: it was
 * replaced, or written under another master key.
 */
final class RecoveryKeyTampered extends RuntimeException {}
