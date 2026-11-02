<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentHousehold
{
    /**
     * Get the path to send the user to: the given page in their current household,
     * or the households page when they do not belong to one yet.
     */
    protected function redirectPathForCurrentHousehold(Request $request, string $redirect): string
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $household = $user->currentHousehold ?? $user->fallbackHousehold();

        if (! $household) {
            return route('households.index', absolute: false);
        }

        $user->switchHousehold($household);

        URL::defaults(['current_household' => $household->ulid]);

        return "/{$household->ulid}{$redirect}";
    }
}
