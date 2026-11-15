<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An Instance Admin enters their section only with a second factor set up: confirmed two-factor
 * authentication or a passkey. A password alone is not enough to run the instance.
 */
class RequireSecondFactor
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at === null && ! $user->hasPasskeysEnabled()) {
            return redirect()->route('security.edit')
                ->with('status', __('Set up two-factor authentication or a passkey to open the Instance Admin section.'));
        }

        return $next($request);
    }
}
