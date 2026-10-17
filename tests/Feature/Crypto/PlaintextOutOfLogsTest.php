<?php

use App\Models\Scopes\HouseholdScope;
use App\Support\Crypto\DataKeyRing;
use App\Support\Crypto\DecryptedValues;
use App\Support\Crypto\Logging\RedactSensitiveData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\Support\Crypto\EncryptedNote;
use Tests\Support\Crypto\RenewNoteJob;
use Tests\Support\Crypto\TestKeys;

/*
 * SEC-11: plaintext stays out of logs, queued jobs and stored job failures.
 */

uses(RefreshDatabase::class);

const LOGS_HOUSEHOLD = '01JAHOUSEHOLDAAAAAAAAAAAAA';

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/abonemen-logs-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->dir);
    config()->set('logging.channels.single.path', "{$this->dir}/laravel.log");
    config()->set('logging.default', 'single');
    Log::forgetChannel('single');

    TestKeys::installRecoveryKey();
    app(DataKeyRing::class)->createHousehold(LOGS_HOUSEHOLD);
    EncryptedNote::createTable();
    app()->instance(HouseholdScope::CONTAINER_KEY, LOGS_HOUSEHOLD);

    $this->note = EncryptedNote::create(['title' => 'Netflix Premium Ünïcødé', 'amount' => '17.99']);
    app(DecryptedValues::class)->forget();
    TestKeys::forget();
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

function theLog(object $test): string
{
    return (string) @file_get_contents("{$test->dir}/laravel.log");
}

it('logs no name or price when an exception is thrown while handling a subscription', function () {
    Route::middleware('web')->get('/_test/renew/{note}', function (string $note) {
        $row = EncryptedNote::query()->findOrFail($note);
        $summarise = fn (string $title, string $amount): never => throw new RuntimeException("Renewal of {$title} for {$amount} failed.");

        $summarise($row->title, (string) $row->amount);
    });

    $this->get("/_test/renew/{$this->note->id}")->assertInternalServerError();

    expect(theLog($this))->toContain('Renewal of [redacted] for [redacted] failed.')
        ->and(theLog($this))->not->toContain('Netflix')
        ->and(theLog($this))->not->toContain('17.99');
});

it('logs no name or price when a queued job fails, and stores none in failed_jobs', function () {
    config()->set('queue.default', 'database');

    RenewNoteJob::dispatch($this->note->id);
    $payload = (string) DB::table('jobs')->value('payload');
    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

    $failure = (string) DB::table('failed_jobs')->value('exception');

    expect($payload)->toContain($this->note->id)
        ->and($payload)->not->toContain('Netflix')
        ->and($failure)->toContain('Could not renew [redacted] at [redacted].')
        ->and($failure)->not->toContain('Netflix')
        ->and($failure)->not->toContain('17.99')
        ->and(theLog($this))->not->toContain('Netflix');
});

it('redacts sensitive keys at any depth, and keeps the rest', function () {
    Log::info('Signing in', [
        'password' => 'hunter22',
        'user' => ['email' => 'alice@example.org', 'id' => '01JUSER'],
        'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        'subscription_count' => 4,
    ]);

    expect(theLog($this))->not->toContain('hunter22')
        ->and(theLog($this))->not->toContain('alice@example.org')
        ->and(theLog($this))->not->toContain('JBSWY3DPEHPK3PXP')
        ->and(theLog($this))->toContain('01JUSER')
        ->and(theLog($this))->toContain('"subscription_count":4');
});

it('scrubs decrypted values under any key, and in previous exceptions', function () {
    $title = EncryptedNote::query()->findOrFail($this->note->id)->title;

    Log::error('Failed', [
        'detail' => "for {$title}",
        'exception' => new LogicException('outer', previous: new RuntimeException("inner {$title}")),
    ]);

    expect(theLog($this))->toContain('Previous: RuntimeException: inner [redacted]')
        ->and(theLog($this))->not->toContain('Netflix');
});

it('scrubs values that were just set, before they are saved', function () {
    $note = new EncryptedNote(['title' => 'Disney Plus Family']);
    Log::warning("Saving {$note->getAttributes()['title']} as Disney Plus Family");

    expect(theLog($this))->not->toContain('Disney Plus Family');
});

describe('the decrypted values', function () {
    it('scrub longer values first and ignore very short ones', function () {
        $values = new DecryptedValues;
        $values->add('Net');
        $values->add('Netflix');
        $values->add('ab');
        $values->add(null);

        expect($values->scrub('Netflix, Net and ab'))->toBe('[redacted], [redacted] and ab');
    });

    it('are emptied when a queued job runs', function () {
        config()->set('queue.default', 'database');
        EncryptedNote::query()->findOrFail($this->note->id)->title;
        expect(app(DecryptedValues::class)->scrub('Netflix Premium Ünïcødé'))->toBe('[redacted]');

        dispatch(function (): void {});
        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        expect(app(DecryptedValues::class)->scrub('Netflix Premium Ünïcødé'))->toBe('Netflix Premium Ünïcødé');
    });
});

it('taps every log channel', function () {
    foreach (config('logging.channels') as $name => $channel) {
        expect([$name => $channel['tap'] ?? []])->toHaveKey($name)
            ->and($channel['tap'] ?? [])->toContain(RedactSensitiveData::class);
    }
});

it('keeps argument values out of stack traces', function () {
    expect(ini_get('zend.exception_ignore_args'))->toBe('1');
});
