<?php

namespace App\Jobs\Platform;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * SPIKE (#15): a job that takes about as long as sending one reminder email,
 * then records when and by which process it ran.
 */
class SpikeQueueProbe implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $number, public int $milliseconds) {}

    public function handle(): void
    {
        usleep($this->milliseconds * 1000);

        DB::table('spike_queue_probes')->where('number', $this->number)->update([
            'finished_at' => now(),
            'worker_pid' => getmypid(),
        ]);
    }
}
