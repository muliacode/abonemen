<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The address the app is reached at, against the one APP_URL says. Links in emails and the domain of passkeys use
 * APP_URL, so a difference makes them point to the wrong place.
 */
class InstanceAddress
{
    public function visited(Request $request): string
    {
        return $request->getSchemeAndHttpHost();
    }

    /**
     * Whether APP_URL names the address being visited. Only the scheme, host and port count, not a path or a slash.
     */
    public function matches(Request $request): bool
    {
        return $this->origin((string) config('app.url')) === $this->origin($this->visited($request));
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return strtolower(($parts['scheme'] ?? 'http').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : ''));
    }
}
