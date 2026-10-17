<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\Support\Leaks\Canaries;
use Tests\Support\Leaks\Canary;
use Tests\Support\Leaks\Leak;
use Tests\Support\Leaks\LeakScanner;

/*
 * SEC-01 and SEC-04: no sensitive value may be readable at rest. The test
 * runs the app against a real SQLite file and real storage folders in a
 * temporary directory, seeds canary values, and searches every byte written
 * there: the database with its WAL and shared-memory files, uploads and
 * other storage, logs and backup archives.
 */

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/abonemen-leak-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists("{$this->root}/database");
    File::ensureDirectoryExists("{$this->root}/storage");
    File::ensureDirectoryExists("{$this->root}/logs");
    touch("{$this->root}/database/database.sqlite");

    config()->set('database.connections.sqlite.database', "{$this->root}/database/database.sqlite");
    config()->set('filesystems.disks.local.root', "{$this->root}/storage");
    config()->set('logging.channels.leak', [
        'driver' => 'single',
        'path' => "{$this->root}/logs/laravel.log",
        'level' => 'debug',
    ]);
    config()->set('logging.default', 'leak');

    DB::purge('sqlite');
    Log::forgetChannel('leak');
    Artisan::call('migrate', ['--force' => true]);
});

afterEach(function () {
    DB::purge('sqlite');
    File::deleteDirectory($this->root);
});

/**
 * @param  list<Leak>  $leaks
 * @return list<string>
 */
function unexpectedLeaks(array $leaks): array
{
    return array_values(array_map(
        'strval',
        array_filter($leaks, fn (Leak $leak): bool => ! array_key_exists($leak->field, Canaries::PLAINTEXT_UNTIL_FIXED)),
    ));
}

it('keeps every canary out of the database, storage and logs', function () {
    $canaries = Canaries::seed();
    Log::info('Seeded the leak-test canaries.');

    expect($canaries)->not->toBeEmpty();

    $leaks = (new LeakScanner($canaries))->scan([$this->root]);

    expect(unexpectedLeaks($leaks))->toBe([]);

    // A field on the plaintext list that no longer leaks has been fixed:
    // remove it from Canaries::PLAINTEXT_UNTIL_FIXED.
    $leakingFields = array_unique(array_map(fn (Leak $leak): string => $leak->field, $leaks));
    foreach (Canaries::PLAINTEXT_UNTIL_FIXED as $field => $issue) {
        expect($leakingFields)->toContain($field);
    }
});

it('keeps every canary out of a backup archive', function () {
    $canaries = Canaries::seed();

    Artisan::call('backup:run', ['--disable-notifications' => true]);

    $leaks = (new LeakScanner($canaries))->scan([$this->root]);

    expect(unexpectedLeaks($leaks))->toBe([]);
})->skip(
    fn (): bool => ! array_key_exists('backup:run', Artisan::all()),
    'Encrypted backups arrive with #85; this test runs once backup:run exists.',
);

describe('the scanner', function () {
    beforeEach(function () {
        $this->canaries = [
            Canary::text('test.text', 'Kanarie Ünïcødé 1b2c3d'),
            Canary::amount('test.amount', 7391483),
        ];
        $this->scanner = new LeakScanner($this->canaries);
    });

    it('finds a canary in the raw SQLite file, including the WAL', function () {
        DB::table('cache')->insert(['key' => 'k', 'value' => 'Kanarie Ünïcødé 1b2c3d', 'expiration' => 0]);

        $leaks = $this->scanner->scan(["{$this->root}/database"]);

        expect(array_map(fn (Leak $leak): string => $leak->field, $leaks))->toContain('test.text');
    });

    it('finds a canary in a nested storage folder', function () {
        File::ensureDirectoryExists("{$this->root}/storage/logos/a");
        file_put_contents("{$this->root}/storage/logos/a/logo.svg", '<svg><title>Kanarie Ünïcødé 1b2c3d</title></svg>');

        expect($this->scanner->scan([$this->root]))->toHaveCount(1);
    });

    it('finds escaped and encoded forms', function (string $stored, string $form) {
        file_put_contents("{$this->root}/storage/file", $stored);

        $forms = array_map(fn (Leak $leak): string => $leak->form, $this->scanner->scan([$this->root]));

        expect($forms)->toContain($form);
    })->with([
        'JSON' => [json_encode(['name' => 'Kanarie Ünïcødé 1b2c3d']), 'JSON-escaped'],
        'URL' => ['?q='.rawurlencode('Kanarie Ünïcødé 1b2c3d'), 'URL-encoded'],
        'base64' => [base64_encode('Kanarie Ünïcødé 1b2c3d'), 'base64'],
        'UTF-16' => [mb_convert_encoding('Kanarie Ünïcødé 1b2c3d', 'UTF-16LE', 'UTF-8'), 'UTF-16'],
        'amount with a point' => ['{"price":"73914.83"}', 'decimal point'],
        'amount with a comma' => ['€ 73914,83', 'decimal comma'],
        'amount in minor units' => ['{"price":7391483}', 'minor units'],
    ]);

    it('finds a canary inside a compressed zip archive and a gzip file', function () {
        $zip = new ZipArchive;
        $zip->open("{$this->root}/storage/backup.zip", ZipArchive::CREATE);
        $zip->addFromString('db-dumps/database.sql', str_repeat('padding ', 100).'Kanarie Ünïcødé 1b2c3d');
        $zip->setCompressionName('db-dumps/database.sql', ZipArchive::CM_DEFLATE);
        $zip->close();
        file_put_contents("{$this->root}/storage/dump.sql.gz", gzencode('INSERT 7391483;'));

        $raw = (string) file_get_contents("{$this->root}/storage/backup.zip");
        expect($raw)->not->toContain('Kanarie');

        $locations = array_map(fn (Leak $leak): string => $leak->location, $this->scanner->scan([$this->root]));

        expect($locations)->toContain("{$this->root}/storage/backup.zip!db-dumps/database.sql")
            ->and($locations)->toContain("{$this->root}/storage/dump.sql.gz!(gzip)");
    });

    it('does not report a canary inside an AES-encrypted zip entry', function () {
        $zip = new ZipArchive;
        $zip->open("{$this->root}/storage/backup.zip", ZipArchive::CREATE);
        $zip->addFromString('database.sqlite', 'Kanarie Ünïcødé 1b2c3d');
        $zip->setEncryptionName('database.sqlite', ZipArchive::EM_AES_256, 'a-random-password');
        $zip->close();

        expect($this->scanner->scan([$this->root]))->toBe([]);
    });

    it('reports nothing for data without canaries', function () {
        file_put_contents("{$this->root}/storage/file", random_bytes(4096));

        expect($this->scanner->scan([$this->root]))->toBe([]);
    });
});
