<?php

namespace App\Providers;

use App\Providers\Auth\EncryptedEmailUserProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AccountsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // config/auth.php: providers.users.driver = encrypted-eloquent
        Auth::provider('encrypted-eloquent', function (Application $app, array $config): EncryptedEmailUserProvider {
            return new EncryptedEmailUserProvider($app->make(Hasher::class), $config['model']);
        });
    }
}
