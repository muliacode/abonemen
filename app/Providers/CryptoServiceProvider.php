<?php

namespace App\Providers;

use App\Support\Crypto\DataKeyRing;
use App\Support\Crypto\HouseholdCipher;
use App\Support\Crypto\MasterKeyLoader;
use App\Support\Crypto\RecoveryKeyStore;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
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
    }

    public static function scrubServerVariables(?Request $request): void
    {
        unset($_SERVER[self::MASTER_KEY_VARIABLE], $_ENV[self::MASTER_KEY_VARIABLE]);
        $request?->server->remove(self::MASTER_KEY_VARIABLE);
    }
}
