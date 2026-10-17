<?php

namespace App\Providers;

use App\Support\Crypto\DataKeyRing;
use App\Support\Crypto\MasterKeyLoader;
use Illuminate\Support\Env;
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

        $this->app->singleton(DataKeyRing::class);
    }
}
