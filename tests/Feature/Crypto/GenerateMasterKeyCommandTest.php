<?php

use App\Support\Crypto\MasterKey;
use App\Support\Crypto\MasterKeyLoader;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/abonemen-mk-'.bin2hex(random_bytes(6));
    $this->path = "{$this->dir}/keys/master.key";
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

it('creates a key file that only its owner can read', function () {
    app()->instance(MasterKeyLoader::class, new MasterKeyLoader(null, $this->path));

    $this->artisan('abonemen:keys:generate-master')->assertSuccessful();

    expect(MasterKey::fromBase64((string) file_get_contents($this->path)))->toBeInstanceOf(MasterKey::class)
        ->and(fileperms($this->path) & 0777)->toBe(0600);
});

it('never replaces an existing key file', function () {
    File::ensureDirectoryExists(dirname($this->path));
    $existing = MasterKey::generate()->toBase64()."\n";
    file_put_contents($this->path, $existing);
    app()->instance(MasterKeyLoader::class, new MasterKeyLoader(null, $this->path));

    $this->artisan('abonemen:keys:generate-master')
        ->expectsOutputToContain('left unchanged')
        ->assertSuccessful();

    expect(file_get_contents($this->path))->toBe($existing);
});

it('fails, and leaves the file alone, when the existing file holds no valid key', function () {
    File::ensureDirectoryExists(dirname($this->path));
    file_put_contents($this->path, 'not a key');
    app()->instance(MasterKeyLoader::class, new MasterKeyLoader(null, $this->path));

    $this->artisan('abonemen:keys:generate-master')->assertFailed();

    expect(file_get_contents($this->path))->toBe('not a key');
});

it('needs ABONEMEN_MASTER_KEY_FILE', function () {
    app()->instance(MasterKeyLoader::class, new MasterKeyLoader(null, null));

    $this->artisan('abonemen:keys:generate-master')
        ->expectsOutputToContain('Set ABONEMEN_MASTER_KEY_FILE')
        ->assertFailed();
});

it('has nothing to create when the key comes from the environment', function () {
    app()->instance(MasterKeyLoader::class, new MasterKeyLoader(MasterKey::generate()->toBase64(), null));

    $this->artisan('abonemen:keys:generate-master')
        ->expectsOutputToContain('comes from ABONEMEN_MASTER_KEY')
        ->assertFailed();
});
