<?php

namespace App\Providers;

use App\Auth\ProtectedUserProvider;
use App\Http\Middleware\EnsureInstanceAdmin;
use App\Http\Middleware\RequireSecondFactor;
use App\Support\KeyRing;
use App\Support\MailConfiguration;
use App\Support\PlatformSettings;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(KeyRing::class);
        $this->app->scoped(PlatformSettings::class);
        $this->app->singleton(MailConfiguration::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureProtectedUsers();

        // A page of the Instance Admin section stays open in a browser, and every action on it is a request of its own.
        // Livewire re-applies only the route middleware it is told to, so the doors of the section are named here.
        Livewire::addPersistentMiddleware([EnsureInstanceAdmin::class, RequireSecondFactor::class]);

        // Email settings an Instance Admin saved in the app win over .env.
        $this->app->make(MailConfiguration::class)->apply();
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
