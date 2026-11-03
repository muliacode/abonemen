<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('master-key:generate {--write : Save the key in the .env file instead of printing it} {--force : Replace a key that is already set}')]
#[Description('Make a new master key for ABONEMEN_MASTER_KEY')]
class GenerateMasterKey extends Command
{
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

        return $this->saveInEnvironmentFile($key);
    }

    private function saveInEnvironmentFile(string $key): int
    {
        $path = $this->laravel->environmentFilePath();

        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            $this->components->error("There is no .env file at {$path}. Copy .env.example to .env first.");

            return self::FAILURE;
        }

        if ($this->keyIsAlreadySet($contents) && ! $this->option('force')) {
            $this->components->warn('A master key is already set and was left unchanged. Replacing it would make everything protected with the old key unreadable; use --force only if you are sure.');

            return self::SUCCESS;
        }

        $setting = self::SETTING.'='.$key;

        $contents = preg_match('/^'.self::SETTING.'=.*$/m', $contents)
            ? preg_replace_callback('/^'.self::SETTING.'=.*$/m', fn () => $setting, $contents)
            : rtrim($contents, "\n")."\n{$setting}\n";

        file_put_contents($path, $contents);

        $this->components->info('Master key saved in .env. Keep a copy somewhere safe: if it is lost, the data it protects cannot be read.');

        return self::SUCCESS;
    }

    private function keyIsAlreadySet(string $contents): bool
    {
        return preg_match('/^'.self::SETTING.'=(.+)$/m', $contents, $matches) === 1 && trim($matches[1]) !== '';
    }
}
