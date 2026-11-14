<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Show the app in the signed-in user's language, or else the one the browser asks for.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // A guest has no user, and a user may not have chosen a language: both fall through to the browser.
        $locale = $request->user()->locale ?? $this->browserLocale($request);

        if ($locale) {
            app()->setLocale($locale->value);
        }

        return $next($request);
    }

    /**
     * Pick the first language in the browser's preference order that the app is available in.
     */
    private function browserLocale(Request $request): ?Locale
    {
        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(explode('-', str_replace('_', '-', $language))[0]);

            if ($locale = Locale::tryFrom($primary)) {
                return $locale;
            }
        }

        return null;
    }
}
