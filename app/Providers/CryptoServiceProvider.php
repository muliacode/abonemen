<?php

namespace App\Providers;

use App\Support\Crypto\DataKeyRing;
use App\Support\Crypto\DecryptedValues;
use App\Support\Crypto\FileVault;
use App\Support\Crypto\HouseholdCipher;
use App\Support\Crypto\Logging\RedactingFailedJobProvider;
use App\Support\Crypto\MasterKeyLoader;
use App\Support\Crypto\RecoveryKeyStore;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CryptoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MasterKeyLoader::class, function (): MasterKeyLoader {
            $value = Env::get('ABONEMEN_MASTER_KEY');

            // Keep the key out of anything that dumps the request's server
            // variables, such as an error page. The loader keeps its own copy.
            unset($_SERVER['ABONEMEN_MASTER_KEY'], $_ENV['ABONEMEN_MASTER_KEY']);

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
}
