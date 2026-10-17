<?php

namespace App\Http\Middleware;

use App\Support\Crypto\MasterKeyLoader;
use App\Support\Crypto\MasterKeyStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Without a usable master key the app serves nothing but a page that says so
 * (SEC-01). The setup wizard (#25) and recovery mode (#140) will be the only
 * routes allowed through.
 */
class EnsureMasterKey
{
    public function __construct(private readonly MasterKeyLoader $loader) {}

    public function handle(Request $request, Closure $next): Response
    {
        $status = $this->loader->status();

        if ($status === MasterKeyStatus::Ready) {
            return $next($request);
        }

        if ($status === MasterKeyStatus::Invalid) {
            Log::error((string) $this->loader->problem());
        }

        return response()
            ->view('crypto.not-ready', ['status' => $status], Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Cache-Control', 'no-store');
    }
}
