<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Instance Admin section does not exist for anyone else: a guest and a Member get the same 404 as a
 * path that was never there, so that its location tells nothing.
 */
class EnsureInstanceAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_instance_admin, 404);

        return $next($request);
    }
}
