<?php

namespace App\Console\Commands;

use App\Console\Concerns\WritesEnvironmentSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('master-key:generate {--write : Save the key in the .env file instead of printing it} {--force : Replace a key that is already set}')]
#[Description('Make a new master key for ABONEMEN_MASTER_KEY')]
class GenerateMasterKey extends Command
{
    use WritesEnvironmentSetting;

    private const SETTING = 'ABONEMEN_MASTER_KEY';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        if (! $this->option('write')) {
            $this->line($key);

            return self::SUCCESS;
        }

        $problem = $this->saveSetting(self::SETTING, $key, (bool) $this->option('force'));

        if ($problem === 'missing') {
            $this->components->error("There is no .env file at {$this->laravel->environmentFilePath()}. Copy .env.example to .env first.");

            return self::FAILURE;
        }

        if ($problem === 'set') {
            $this->components->warn('A master key is already set and was left unchanged. Replacing it would make everything protected with the old key unreadable; use --force only if you are sure.');

            return self::SUCCESS;
        }

        $this->components->info('Master key saved in .env. Keep a copy somewhere safe: if it is lost, the data it protects cannot be read.');

        return self::SUCCESS;
    }
}
