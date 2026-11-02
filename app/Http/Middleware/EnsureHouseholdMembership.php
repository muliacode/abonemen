<?php

namespace App\Http\Middleware;

use App\Models\Household;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHouseholdMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        [$user, $household] = [$request->user(), $this->household($request)];

        abort_if(! $user || ! $household || ! $user->belongsToHousehold($household), 403);

        if ($request->route('current_household') && ! $user->isCurrentHousehold($household)) {
            $user->switchHousehold($household);
        }

        return $next($request);
    }

    /**
     * Get the household associated with the request.
     */
    protected function household(Request $request): ?Household
    {
        $household = $request->route('current_household') ?? $request->route('household');

        if (is_string($household)) {
            $household = Household::where('ulid', $household)->first();
        }

        return $household;
    }
}
