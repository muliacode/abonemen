<?php

namespace App\Http\Controllers\Crypto;

use App\Http\Controllers\Controller;
use App\Support\Crypto\ServerCheck;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPIKE (#15): the shared-hosting check as a web page, for hosts without SSH.
 * Protected by SPIKE_TOKEN; disabled when it is not set.
 */
class SpikeServerCheckController extends Controller
{
    public function show(Request $request): View
    {
        $this->authorizeToken($request);

        if (! Schema::hasTable('spike_queue_probes')) {
            return view('spike.prepare', ['token' => $request->query('token')]);
        }

        $root = $request->server('DOCUMENT_ROOT');
        $documentRoot = is_string($root) ? (realpath($root) ?: $root) : '';
        $publicIsRoot = $documentRoot === realpath(public_path());

        return view('spike.check', [
            'checks' => (new ServerCheck)->run(),
            'documentRoot' => $documentRoot,
            'publicIsRoot' => $publicIsRoot,
            'token' => $request->query('token'),
            'probes' => [
                'total' => DB::table('spike_queue_probes')->count(),
                'done' => DB::table('spike_queue_probes')->whereNotNull('finished_at')->count(),
                'last' => DB::table('spike_queue_probes')->max('finished_at'),
            ],
            'runs' => DB::table('spike_cron_runs')->orderBy('id')->get(),
        ]);
    }

    public function prepare(Request $request): RedirectResponse
    {
        $this->authorizeToken($request);

        Artisan::call('migrate', ['--force' => true]);

        return redirect()->route('spike.check', ['token' => $request->query('token')]);
    }

    public function fillQueue(Request $request): RedirectResponse
    {
        $this->authorizeToken($request);

        Artisan::call('spike:queue-fill', ['count' => 100, '--ms' => 500]);

        return redirect()->route('spike.check', ['token' => $request->query('token')]);
    }

    private function authorizeToken(Request $request): void
    {
        $token = config('app.spike_token');

        abort_unless(is_string($token) && $token !== '' && hash_equals($token, (string) $request->query('token')), 404);
    }
}
