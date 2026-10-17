<?php

namespace App\Console\Commands\Crypto;

use App\Actions\Crypto\CreateMasterKeyFile;
use App\Support\Crypto\InvalidMasterKey;
use App\Support\Crypto\MasterKeyLoader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('abonemen:keys:generate-master')]
#[Description('Create the master key file at ABONEMEN_MASTER_KEY_FILE, unless it already exists')]
class GenerateMasterKeyCommand extends Command
{
    public function handle(MasterKeyLoader $loader, CreateMasterKeyFile $create): int
    {
        $path = $loader->filePath();

        // With both set the app refuses to start, and a second, different key
        // would make it ambiguous which one wraps the data keys.
        if ($loader->usesEnvironment()) {
            $this->components->error($path === null
                ? 'The master key comes from ABONEMEN_MASTER_KEY; there is no file to create.'
                : 'ABONEMEN_MASTER_KEY is set, so no key file was created. Use one form: unset ABONEMEN_MASTER_KEY or ABONEMEN_MASTER_KEY_FILE.');

            return self::FAILURE;
        }

        if ($path === null) {
            $this->components->error('Set ABONEMEN_MASTER_KEY_FILE to the path of the key file first.');

            return self::FAILURE;
        }

        try {
            $created = $create($path);
        } catch (InvalidMasterKey) {
            $this->components->error("{$path} exists but holds no valid master key. It was left unchanged.");

            return self::FAILURE;
        }

        if (! $created) {
            $this->components->info("A master key already exists at {$path}; it was left unchanged.");

            return self::SUCCESS;
        }

        $this->components->info("Created a master key at {$path}.");
        $this->components->warn('Back this file up somewhere safe and keep it out of backups of the server: without it, or the recovery key, the data cannot be read.');

        return self::SUCCESS;
    }
}
