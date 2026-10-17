<?php

namespace App\Actions\Accounts;

use Illuminate\Http\Request;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;

/**
 * Fortify's password sign-in step that sends the user to the two-factor
 * challenge. Fortify only knows TOTP; Abonemen also asks for the second factor
 * when the user has a passkey, which may then answer the challenge (ACC-02).
 */
class RedirectIfSecondFactorRequired extends RedirectIfTwoFactorAuthenticatable
{
    /**
     * @param  Request  $request
     * @param  callable  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        $user = $this->validateCredentials($request);

        $hasTotp = $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
        $hasPasskey = $user instanceof PasskeyUser && $user->passkeys()->exists();

        if ($hasTotp || $hasPasskey) {
            return $this->twoFactorChallengeResponse($request, $user);
        }

        return $next($request);
    }
}
