<?php

namespace App\Providers;

use App\Actions\Accounts\RedirectIfSecondFactorRequired;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

/**
 * SPIKE (#13): Fortify with Inertia pages, TOTP and passkeys.
 */
class AccountsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->scoped(RedirectsIfTwoFactorAuthenticatable::class, RedirectIfSecondFactorRequired::class);

        Fortify::loginView(fn () => Inertia::render('Accounts/Login'));
        Fortify::twoFactorChallengeView(fn (Request $request) => Inertia::render('Accounts/TwoFactorChallenge', [
            'hasTotp' => $this->challengedUser($request)?->two_factor_confirmed_at !== null,
            'hasPasskey' => (bool) $this->challengedUser($request)?->passkeys()->exists(),
        ]));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input(Fortify::username())).'|'.$request->ip()));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by((string) $request->session()->get('login.id')));
        RateLimiter::for('passkeys', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }

    private function challengedUser(Request $request): ?User
    {
        $id = $request->session()->get('login.id');

        return is_scalar($id) ? User::query()->whereKey($id)->first() : null;
    }
}
