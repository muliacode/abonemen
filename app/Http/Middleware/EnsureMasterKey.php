<?php

namespace App\Http\Middleware;

use App\Support\Crypto\MasterKeyLoader;
use App\Support\Crypto\MasterKeyStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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

        // Once an hour, not on every request: a public instance with a broken
        // key file would otherwise fill the log and keep alerts firing.
        if ($status === MasterKeyStatus::Invalid && $this->firstReportThisHour()) {
            Log::error((string) $this->loader->problem());
        }

        return response()
            ->view('crypto.not-ready', ['status' => $status], Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Cache-Control', 'no-store');
    }

    private function firstReportThisHour(): bool
    {
        try {
            return Cache::add('abonemen:master-key-problem-reported', true, now()->addHour());
        } catch (Throwable) {
            // Without a working cache, logging every time beats not logging.
            return true;
        }
    }
}
