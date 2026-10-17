<?php

namespace App\Support\Crypto;

use RuntimeException;

/**
 * A typed recovery key cannot be read. The message never repeats what was typed.
 */
final class InvalidRecoverySecret extends RuntimeException {}
