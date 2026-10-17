<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SPIKE (#16): picks the interface language from the session, then from the
 * browser's Accept-Language header, limited to the supported locales.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales');

        $chosen = $request->session()->get('locale');
        $locale = is_string($chosen) && in_array($chosen, $supported, true)
            ? $chosen
            : $request->getPreferredLanguage($supported);

        app()->setLocale($locale ?? config('app.locale'));

        return $next($request);
    }
}
