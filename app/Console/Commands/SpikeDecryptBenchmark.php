<?php

namespace App\Console\Commands;

use App\Models\Scopes\HouseholdScope;
use App\Models\Subscription;
use App\Support\Crypto\HouseholdCipher;
use App\Support\Crypto\HouseholdKeyRing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SPIKE (#12): how long does decrypting a household's subscriptions take?
 * NFR-02 leaves 100 ms of the 300 ms page budget for 500 subscriptions.
 */
class SpikeDecryptBenchmark extends Command
{
    protected $signature = 'spike:decrypt-benchmark {--rows=500} {--runs=30} {--backend=fast : fast or boring}';

    protected $description = 'SPIKE (#12): measure decrypting a household\'s subscriptions';

    private const FIELDS = ['name', 'price', 'currency', 'notes', 'url'];

    public function handle(): int
    {
        $rows = (int) $this->option('rows');
        $runs = (int) $this->option('runs');

        $path = storage_path('framework/testing/spike-benchmark.sqlite');
        @unlink($path);
        @unlink("{$path}-wal");
        @unlink("{$path}-shm");
        touch($path);
        config()->set('database.connections.sqlite.database', $path);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);

        $backend = $this->option('backend') === 'boring' ? 'CipherSweet BoringCrypto' : 'FastBoringCrypto';
        if ($this->option('backend') === 'boring') {
            app()->instance(HouseholdCipher::class, HouseholdCipher::withStockBackend(app(HouseholdKeyRing::class)));
        }

        $household = (string) Str::ulid();
        app(HouseholdKeyRing::class)->add($household, random_bytes(32));
        app()->instance(HouseholdScope::CONTAINER_KEY, $household);

        DB::transaction(function () use ($rows): void {
            for ($i = 0; $i < $rows; $i++) {
                Subscription::create([
                    'name' => "Subscription {$i}",
                    'price' => (string) random_int(100, 99999),
                    'currency' => 'EUR',
                    'notes' => str_repeat('Some notes about this subscription. ', 3),
                    'url' => "https://example.com/account/{$i}",
                    'billing_cycle' => 'monthly',
                    'next_payment_on' => '2026-11-01',
                ]);
            }
        });

        $results = [
            'query only' => $this->measure($runs, fn (): int => count(Subscription::all()->all())),
            'query + decrypt all 5 fields' => $this->measure($runs, function (): void {
                $this->readFields(self::FIELDS);
            }),
            'query + decrypt name only' => $this->measure($runs, function (): void {
                $this->readFields(['name']);
            }),
            'cold: + derive field keys first' => $this->measure($runs, function (): void {
                app(HouseholdCipher::class)->flush();
                $this->readFields(self::FIELDS);
            }),
        ];

        $this->info(sprintf('%d subscriptions, %d runs, %s, PHP %s, %s', $rows, $runs, $backend, PHP_VERSION, php_uname('m')));
        $this->table(
            ['Measurement', 'Median (ms)', 'p95 (ms)'],
            array_map(fn (string $name, array $r): array => [$name, $r['median'], $r['p95']], array_keys($results), $results),
        );

        return self::SUCCESS;
    }

    /**
     * Loads every subscription and reads (so decrypts) the given fields.
     *
     * @param  list<string>  $fields
     */
    private function readFields(array $fields): int
    {
        $bytes = 0;
        foreach (Subscription::all() as $subscription) {
            foreach ($fields as $field) {
                $bytes += strlen((string) $subscription->getAttribute($field));
            }
        }

        return $bytes;
    }

    /**
     * @return array{median: string, p95: string}
     */
    private function measure(int $runs, callable $work): array
    {
        $work(); // warm-up

        $times = [];
        for ($i = 0; $i < $runs; $i++) {
            $start = hrtime(true);
            $work();
            $times[] = (hrtime(true) - $start) / 1e6;
        }
        sort($times);

        return [
            'median' => number_format($times[intdiv($runs, 2)], 1),
            'p95' => number_format($times[(int) floor(($runs - 1) * 0.95)], 1),
        ];
    }
}
