<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Support\PlatformSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private PlatformSettings $settings)
    {
        //
    }

    /**
     * Show the app in the signed-in user's language, or else the one the browser asks for, or else the one the
     * Instance Admin chose as the instance default.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // A guest has no user, and a user may not have chosen a language: both fall through to the browser,
        // and a browser the app is not available in falls through to the instance default.
        $locale = $request->user()->locale ?? $this->browserLocale($request) ?? $this->settings->defaultLanguage();

        app()->setLocale($locale->value);

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
