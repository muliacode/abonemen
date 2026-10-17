<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Support\WebAuthn;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * A passkey as the second factor after a password: the two-factor challenge's
 * passkey option. Only the challenged user's own passkeys are offered and
 * accepted, unlike passwordless sign-in, which accepts any registered passkey.
 */
class PasskeyChallengeController extends Controller
{
    private const SESSION_KEY = 'passkey.two_factor_options';

    public function options(Request $request, GenerateVerificationOptions $generate): JsonResponse
    {
        $options = $generate($this->challengedUser($request));

        $request->session()->put(self::SESSION_KEY, WebAuthn::toJson($options));

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function store(PasskeyVerificationRequest $request, VerifyPasskey $verify, StatefulGuard $guard): JsonResponse
    {
        $user = $this->challengedUser($request);

        $serialized = $request->session()->pull(self::SESSION_KEY);
        if (! is_string($serialized)) {
            throw ValidationException::withMessages(['credential' => __('Passkey verification session expired. Please try again.')]);
        }

        $verify($request->credential(), WebAuthn::fromJson($serialized, PublicKeyCredentialRequestOptions::class), $user);

        $guard->login($user, (bool) $request->session()->pull('login.remember', false));
        $request->session()->forget('login.id');
        $request->session()->regenerate();

        event(new ValidTwoFactorAuthenticationCodeProvided($user));

        return response()->json(['redirect' => Fortify::redirects('login', config('fortify.home'))]);
    }

    private function challengedUser(Request $request): User
    {
        $id = $request->session()->get('login.id');
        $user = is_scalar($id) ? User::query()->whereKey($id)->first() : null;

        if ($user === null) {
            throw ValidationException::withMessages(['credential' => __('Sign in with your password first.')]);
        }

        return $user;
    }
}
