<?php

namespace App\Providers;

use App\Support\Crypto\DataKeyRing;
use App\Support\Crypto\DecryptedValues;
use App\Support\Crypto\FileVault;
use App\Support\Crypto\HouseholdCipher;
use App\Support\Crypto\Logging\RedactingFailedJobProvider;
use App\Support\Crypto\MasterKeyLoader;
use App\Support\Crypto\RecoveryKeyStore;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CryptoServiceProvider extends ServiceProvider
{
    public const MASTER_KEY_VARIABLE = 'ABONEMEN_MASTER_KEY';

    public function register(): void
    {
        // Read the key, then remove it from every place a debug error page or a
        // dump of the request could show it, before anything else runs: the
        // superglobals and the request, which was captured before the app
        // booted. The loader keeps the only copy.
        $value = Env::get(self::MASTER_KEY_VARIABLE);
        $request = $this->app->bound('request') ? $this->app->make('request') : null;
        self::scrubServerVariables($request instanceof Request ? $request : null);

        $this->app->singleton(MasterKeyLoader::class, function () use ($value): MasterKeyLoader {
            $path = config('abonemen.master_key_file');

            return new MasterKeyLoader(
                is_string($value) ? $value : null,
                is_string($path) ? $path : null,
            );
        });

        $this->app->singleton(RecoveryKeyStore::class);
        $this->app->singleton(DataKeyRing::class);
        $this->app->singleton(HouseholdCipher::class);
        $this->app->singleton(FileVault::class);
        $this->app->singleton(DecryptedValues::class);

        $this->app->extend('queue.failer', fn (FailedJobProviderInterface $failer): RedactingFailedJobProvider => new RedactingFailedJobProvider($failer));
    }

    public function boot(): void
    {
        // Stack traces never carry argument values, which could be plaintext
        // and which PHP cuts to 15 characters, too short to scrub (SEC-11).
        ini_set('zend.exception_ignore_args', '1');

        // Each job starts with nothing decrypted and nothing unwrapped, and a
        // successful one ends that way: a worker runs many jobs in one
        // process. Not on JobFailed: Laravel stores the failure in a JobFailed
        // listener of its own, which still needs the values to scrub them.
        $forget = function (): void {
            $this->app->make(DecryptedValues::class)->forget();
            $this->app->make(DataKeyRing::class)->forget();
            $this->app->make(HouseholdCipher::class)->forget();
        };
        Event::listen(JobProcessing::class, $forget);
        Event::listen(JobProcessed::class, $forget);
    }

    public static function scrubServerVariables(?Request $request): void
    {
        unset($_SERVER[self::MASTER_KEY_VARIABLE], $_ENV[self::MASTER_KEY_VARIABLE]);
        $request?->server->remove(self::MASTER_KEY_VARIABLE);
    }
}
