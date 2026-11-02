<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetHouseholdUrlDefaults
{
    /**
     * Set the default URL parameters for household-based routes.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($currentHousehold = $request->user()?->currentHousehold) {
            URL::defaults(['current_household' => $currentHousehold->ulid]);
        }

        return $next($request);
    }
}
