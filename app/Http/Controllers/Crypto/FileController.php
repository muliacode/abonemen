<?php

namespace App\Http\Controllers\Crypto;

use App\Http\Controllers\Controller;
use App\Models\Scopes\HouseholdScope;
use App\Models\StoredFile;
use App\Support\Crypto\FileVault;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Serves an encrypted upload to members of its household only (SEC-04).
 *
 * The current household is bound by the HTTP layer (#31 adds membership);
 * guests, requests without a household and other households get 403. Guests
 * are refused here rather than by the auth middleware, which would redirect to
 * a sign-in page that does not exist yet.
 */
class FileController extends Controller
{
    public function show(string $file, FileVault $vault): Response
    {
        $household = app()->bound(HouseholdScope::CONTAINER_KEY) ? app(HouseholdScope::CONTAINER_KEY) : null;
        abort_if(Auth::guest() || $household === null, 403);

        $stored = StoredFile::query()->withoutGlobalScope(HouseholdScope::class)->find($file);
        abort_if($stored === null, 404);
        abort_if($stored->household_id !== (string) $household, 403);

        return response($vault->read($stored), 200, [
            'Content-Type' => $stored->mime,
            'Content-Length' => (string) $stored->size,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
            'Content-Disposition' => 'inline',
        ]);
    }
}
