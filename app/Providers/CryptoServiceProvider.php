<?php

namespace App\Providers;

use App\Support\Crypto\GlobalEncrypter;
use App\Support\Crypto\HouseholdCipher;
use App\Support\Crypto\HouseholdKeyRing;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class CryptoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HouseholdKeyRing::class);
        $this->app->singleton(HouseholdCipher::class);
    }

    public function boot(): void
    {
        // Fortify's TOTP secret and recovery codes are encrypted under the GDK, not APP_KEY.
        Fortify::encryptUsing(new GlobalEncrypter('fortify-two-factor'));
    }
}
