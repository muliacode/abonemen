<?php

use App\Actions\Crypto\CreateGlobalDataKey;
use App\Models\Scopes\HouseholdScope;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\Crypto\DataKeyRing;
use App\Support\Crypto\FileDecryptionFailed;
use App\Support\Crypto\FileVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Crypto\TestKeys;

/*
 * SEC-04: uploads are encrypted with libsodium secretstream under a key derived
 * from the household data key and the file's ULID, stored outside the web root
 * and served only to the household, with private, no-store caching.
 */

uses(RefreshDatabase::class);

const FILES_A = '01JAHOUSEHOLDAAAAAAAAAAAAA';
const FILES_B = '01JBHOUSEHOLDBBBBBBBBBBBBB';

beforeEach(function () {
    Storage::fake(FileVault::DISK);
    TestKeys::installRecoveryKey();
    app(DataKeyRing::class)->createHousehold(FILES_A);
    app(DataKeyRing::class)->createHousehold(FILES_B);
    $this->vault = app(FileVault::class);
});

function freshFile(string $id): StoredFile
{
    TestKeys::forget();

    return StoredFile::query()->withoutGlobalScope(HouseholdScope::class)->findOrFail($id);
}

describe('the vault', function () {
    it('encrypts and decrypts files of any size', function (string $contents) {
        $file = $this->vault->store(FILES_A, $contents, 'application/octet-stream');
        $onDisk = Storage::disk(FileVault::DISK)->get($file->path);

        expect($this->vault->read(freshFile($file->id)))->toBe($contents)
            ->and($file->size)->toBe(strlen($contents))
            ->and($onDisk)->toStartWith('ABF1');

        if (strlen($contents) >= 16) {
            expect($onDisk)->not->toContain(substr($contents, 0, 16));
        }
    })->with([
        'empty' => [''],
        'a small SVG' => ['<svg xmlns="http://www.w3.org/2000/svg"><title>Netflix logo</title></svg>'],
        'exactly one chunk' => [str_repeat('a', 65536)],
        'several chunks' => [random_bytes(200_000)],
    ]);

    it('stores the file under its household, with the key version', function () {
        $file = $this->vault->store(FILES_A, 'logo', 'image/png');

        expect($file->path)->toBe(FILES_A.'/'.$file->id)
            ->and($file->key_version)->toBe(1)
            ->and($file->toArray())->not->toHaveKey('path');
    });

    it('keeps reading old files after a new household key version', function () {
        $file = $this->vault->store(FILES_A, 'old logo', 'image/png');
        app(DataKeyRing::class)->createHousehold(FILES_A);

        expect($this->vault->read(freshFile($file->id)))->toBe('old logo')
            ->and($this->vault->store(FILES_A, 'new', 'image/png')->key_version)->toBe(2);
    });

    it('refuses a file that was tampered with', function (Closure $tamper) {
        $file = $this->vault->store(FILES_A, random_bytes(70_000), 'image/png');
        $disk = Storage::disk(FileVault::DISK);
        $disk->put($file->path, $tamper((string) $disk->get($file->path)));

        expect(fn () => $this->vault->read(freshFile($file->id)))->toThrow(FileDecryptionFailed::class);
    })->with([
        'one bit flipped' => [function (string $data): string {
            $data[100] = chr(ord($data[100]) ^ 1);

            return $data;
        }],
        'last chunk cut off' => [fn (string $data): string => substr($data, 0, 4 + 24 + 65536 + 17)],
        'bytes appended' => [fn (string $data): string => $data.random_bytes(40)],
        'not a vault file' => [fn (string $data): string => 'PNG'.substr($data, 3)],
    ]);

    it('refuses a file moved to another household', function () {
        $file = $this->vault->store(FILES_A, 'logo', 'image/png');
        $file->update(['household_id' => FILES_B]);

        expect(fn () => $this->vault->read(freshFile($file->id)))->toThrow(FileDecryptionFailed::class);
    });

    it('refuses a file copied under another id', function () {
        $a = $this->vault->store(FILES_A, 'logo A', 'image/png');
        $b = $this->vault->store(FILES_A, 'logo B', 'image/png');
        Storage::disk(FileVault::DISK)->put($b->path, (string) Storage::disk(FileVault::DISK)->get($a->path));

        expect(fn () => $this->vault->read(freshFile($b->id)))->toThrow(FileDecryptionFailed::class);
    });

    it('says a file is missing with FileDecryptionFailed', function () {
        config()->set('filesystems.disks.encrypted', [...config('filesystems.disks.encrypted'), 'root' => sys_get_temp_dir().'/abonemen-vault-'.bin2hex(random_bytes(4))]);
        Storage::forgetDisk(FileVault::DISK);
        $file = $this->vault->store(FILES_A, 'logo', 'image/png');
        Storage::disk(FileVault::DISK)->delete($file->path);

        expect(fn () => $this->vault->read(freshFile($file->id)))->toThrow(FileDecryptionFailed::class, 'missing');
    });

    it('leaves no file behind when its row cannot be saved', function () {
        StoredFile::saving(fn () => throw new RuntimeException('database down'));

        expect(fn () => $this->vault->store(FILES_A, 'logo', 'image/png'))->toThrow(RuntimeException::class, 'database down')
            ->and(Storage::disk(FileVault::DISK)->allFiles())->toBe([]);
    });

    it('deletes the file and its row', function () {
        $file = $this->vault->store(FILES_A, 'logo', 'image/png');
        $this->vault->delete($file);

        expect(Storage::disk(FileVault::DISK)->exists($file->path))->toBeFalse()
            ->and(StoredFile::query()->withoutGlobalScope(HouseholdScope::class)->count())->toBe(0);
    });
});

describe('serving', function () {
    beforeEach(function () {
        app(CreateGlobalDataKey::class)();
        $this->member = User::create(['name' => 'Member', 'email' => 'member@example.org', 'password' => 'secret secret']);
        $this->file = $this->vault->store(FILES_A, '<svg><title>Logo</title></svg>', 'image/svg+xml');
    });

    it('serves the file to its household, privately and sandboxed', function () {
        app()->instance(HouseholdScope::CONTAINER_KEY, FILES_A);

        $this->actingAs($this->member)
            ->get(route('files.show', $this->file->id))
            ->assertOk()
            ->assertContent('<svg><title>Logo</title></svg>')
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', 'sandbox');
    });

    it("gives another household's user 403", function () {
        app()->instance(HouseholdScope::CONTAINER_KEY, FILES_B);

        $this->actingAs($this->member)->get(route('files.show', $this->file->id))->assertForbidden();
    });

    it('gives guests and requests without a household 403', function () {
        $this->get(route('files.show', $this->file->id))->assertForbidden();
        $this->actingAs($this->member)->get(route('files.show', $this->file->id))->assertForbidden();
    });

    it('gives 404 for an unknown file', function () {
        app()->instance(HouseholdScope::CONTAINER_KEY, FILES_A);

        $this->actingAs($this->member)->get(route('files.show', '01JZZZZZZZZZZZZZZZZZZZZZZZ'))->assertNotFound();
    });

    it('cannot be reached by a direct file URL', function () {
        $onDisk = (string) Storage::disk(FileVault::DISK)->get($this->file->path);

        foreach (["/storage/{$this->file->path}", "/storage/app/encrypted/{$this->file->path}", "/encrypted/{$this->file->path}", "/{$this->file->path}"] as $url) {
            $response = $this->get($url);

            expect($response->status())->toBeIn([403, 404])
                ->and((string) $response->getContent())->not->toContain('<title>Logo</title>')
                ->and((string) $response->getContent())->not->toContain(substr($onDisk, 4, 24));
        }
    });
});

it('keeps the encrypted disk outside the web root and off the /storage route', function () {
    $root = (string) config('filesystems.disks.encrypted.root');

    expect(str_starts_with(realpath(dirname($root)) ?: $root, public_path()))->toBeFalse()
        ->and(config('filesystems.disks.encrypted.serve'))->toBeFalse();
});
