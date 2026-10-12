<?php

use Illuminate\Support\Facades\DB;

it('runs SQLite in WAL mode', function () {
    $path = tempnam(sys_get_temp_dir(), 'abonemen-wal-');

    config()->set('database.connections.wal_check', [
        ...config('database.connections.sqlite'),
        'database' => $path,
    ]);

    try {
        $mode = DB::connection('wal_check')->selectOne('PRAGMA journal_mode');

        expect(strtolower($mode->journal_mode))->toBe('wal');
    } finally {
        DB::purge('wal_check');

        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($path.$suffix);
        }
    }
});
