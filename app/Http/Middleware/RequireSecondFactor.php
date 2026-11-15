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
        if (! $request->user()->hasSecondFactor()) {
            // The Security page says why, because it asks for the password first and a flashed message would be lost.
            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
