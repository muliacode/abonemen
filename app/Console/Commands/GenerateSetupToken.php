<?php

namespace App\Console\Commands;

use App\Console\Concerns\WritesEnvironmentSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('setup-token:generate {--write : Save the token in the .env file instead of printing it} {--force : Replace a token that is already set}')]
#[Description('Make a setup token for ABONEMEN_SETUP_TOKEN, which lets you create the first Instance Admin at /setup')]
class GenerateSetupToken extends Command
{
    use WritesEnvironmentSetting;

    private const SETTING = 'ABONEMEN_SETUP_TOKEN';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $token = bin2hex(random_bytes(24));

        if (! $this->option('write')) {
            $this->line($token);

            return self::SUCCESS;
        }

        $problem = $this->saveSetting(self::SETTING, $token, (bool) $this->option('force'));

        if ($problem === 'missing') {
            $this->components->error("There is no .env file at {$this->laravel->environmentFilePath()}. Copy .env.example to .env first.");

            return self::FAILURE;
        }

        if ($problem === 'set') {
            $this->components->info('A setup token is already set and was left unchanged.');

            return self::SUCCESS;
        }

        $this->components->info('Setup token saved in .env as ABONEMEN_SETUP_TOKEN. Open /setup and enter it to create the first Instance Admin.');

        return self::SUCCESS;
    }
}
