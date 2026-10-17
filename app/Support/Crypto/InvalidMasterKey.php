<?php

namespace App\Support\Crypto;

use RuntimeException;

/**
 * The master key is configured but cannot be used. The message never contains
 * the key or the contents of the key file.
 */
final class InvalidMasterKey extends RuntimeException {}
