<?php

use App\Jobs\Platform\SpikeQueueProbe;
use App\Support\Crypto\ServerCheck;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * SPIKE (#15): cron mode. One cron entry runs `schedule:run` every minute; each
 * run records itself, then works the database queue until it is empty or 50
 * seconds have passed, so the next run never overlaps.
 */
if (config('app.run_mode') === 'cron') {
    Schedule::call(fn () => DB::table('spike_cron_runs')->insert([
        'started_at' => now(),
        'pending_at_start' => DB::table('jobs')->count(),
        'php_version' => PHP_VERSION,
    ]))->everyMinute()->name('spike-cron-heartbeat');

    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
        ->everyMinute()
        ->withoutOverlapping(2);
}

Artisan::command('spike:server-check {--no-network}', function () {
    $checks = (new ServerCheck)->run(! $this->option('no-network'));
    $this->table(['Check', 'Status', 'Detail'], array_map(fn (array $c): array => [$c['name'], strtoupper($c['status']), $c['detail']], $checks));

    return collect($checks)->contains('status', 'fail') ? 1 : 0;
})->purpose('SPIKE (#15): check this server for what Abonemen needs');

Artisan::command('spike:queue-fill {count=100} {--ms=500 : how long each job takes}', function () {
    DB::table('spike_queue_probes')->delete();
    DB::table('spike_cron_runs')->delete();

    $count = (int) $this->argument('count');
    $milliseconds = (int) $this->option('ms');
    for ($i = 1; $i <= $count; $i++) {
        DB::table('spike_queue_probes')->insert(['number' => $i, 'queued_at' => now()]);
        SpikeQueueProbe::dispatch($i, $milliseconds);
    }

    $this->info("Queued {$count} jobs of {$milliseconds} ms. Now wait for cron and run spike:queue-report.");
})->purpose('SPIKE (#15): queue jobs for the cron-mode test');

Artisan::command('spike:queue-report', function () {
    $total = DB::table('spike_queue_probes')->count();
    $done = DB::table('spike_queue_probes')->whereNotNull('finished_at')->count();
    $runs = DB::table('spike_cron_runs')->orderBy('id')->get();
    $drainedBy = $runs->search(fn (object $run): bool => (int) $run->pending_at_start === 0);

    $this->info("{$done} of {$total} jobs done, after {$runs->count()} cron runs.");
    $this->table(['Run', 'Started', 'Jobs waiting at start', 'PHP'], $runs->map(fn (object $r, int $i): array => [$i + 1, $r->started_at, $r->pending_at_start, $r->php_version])->all());

    if ($total > 0 && $done === $total) {
        $finished = DB::table('spike_queue_probes')->max('finished_at');
        $workRuns = $drainedBy === false ? $runs->count() : $drainedBy;
        $this->info("Drained: the last job finished at {$finished}, within {$workRuns} cron run(s).");
    }
})->purpose('SPIKE (#15): show how the cron-mode test went');
