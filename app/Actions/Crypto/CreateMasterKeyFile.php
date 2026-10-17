<?php

namespace App\Actions\Crypto;

use App\Support\Crypto\InvalidMasterKey;
use App\Support\Crypto\MasterKey;
use RuntimeException;

/**
 * Writes a new master key to a file that only its owner can read. It never
 * replaces an existing file: a new master key cannot unwrap the data keys of
 * the old one, so overwriting would lose every household's data.
 */
final class CreateMasterKeyFile
{
    /**
     * @return bool True when a key was written; false when a valid key file
     *              already exists and was left alone.
     *
     * @throws InvalidMasterKey When the file exists but holds no valid key.
     */
    public function __invoke(string $path): bool
    {
        if (file_exists($path)) {
            MasterKey::fromBase64((string) file_get_contents($path));

            return false;
        }

        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create the folder {$directory}.");
        }

        $previous = umask(0077);
        try {
            $handle = fopen($path, 'x');
            if ($handle === false) {
                throw new RuntimeException("Cannot create {$path}.");
            }
            fwrite($handle, MasterKey::generate()->toBase64()."\n");
            fclose($handle);
        } finally {
            umask($previous);
        }

        chmod($path, 0600);

        return true;
    }
}
