<?php

namespace App\Providers;

use App\Auth\ProtectedUserProvider;
use App\Support\KeyRing;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(KeyRing::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureProtectedUsers();
    }

    /**
     * Look users up by the blind index of their encrypted email, and keep plain emails out of reset tokens.
     */
    protected function configureProtectedUsers(): void
    {
        Auth::provider('protected', fn ($app, array $config) => new ProtectedUserProvider($app['hash'], $config['model']));

        ResetPassword::createUrlUsing(fn ($notifiable, string $token) => url(route('password.reset', [
            'token' => $token,
            'email' => $notifiable->email,
        ], false)));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
