<?php

use App\Models\Scopes\HouseholdScope;
use App\Support\Crypto\DataKeyRing;
use App\Support\Crypto\FastBoringCrypto;
use App\Support\Crypto\FieldDecryptionFailed;
use App\Support\Crypto\HouseholdCipher;
use App\Support\Crypto\MasterKeyLoader;
use App\Support\Crypto\MasterKeyMissing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ParagonIE\CipherSweet\Backend\BoringCrypto;
use ParagonIE\CipherSweet\Backend\Key\SymmetricKey;
use Tests\Support\Crypto\EncryptedNote;
use Tests\Support\Crypto\EncryptedNoteTraitsReversed;
use Tests\Support\Crypto\TestKeys;
use Tests\Support\Leaks\Canary;
use Tests\Support\Leaks\LeakScanner;

/*
 * SEC-01: household fields are encrypted with CipherSweet's multi-tenant mode
 * under the household's data key, bound to their row and household.
 */

uses(RefreshDatabase::class);

const NOTES_A = '01JAHOUSEHOLDAAAAAAAAAAAAA';
const NOTES_B = '01JBHOUSEHOLDBBBBBBBBBBBBB';

function inHousehold(string $householdId): void
{
    app()->instance(HouseholdScope::CONTAINER_KEY, $householdId);
}

function rawNote(string $id): object
{
    return DB::table('encrypted_notes')->where('id', $id)->sole();
}

beforeEach(function () {
    TestKeys::installRecoveryKey();
    app(DataKeyRing::class)->createHousehold(NOTES_A);
    app(DataKeyRing::class)->createHousehold(NOTES_B);
    EncryptedNote::createTable();
    inHousehold(NOTES_A);
});

it('stores ciphertext and reads back the plaintext', function () {
    $note = EncryptedNote::create(['title' => 'Netflix Ünïcødé', 'amount' => '15.99']);
    TestKeys::forget();

    $raw = rawNote($note->id);
    $fresh = EncryptedNote::query()->findOrFail($note->id);

    expect($raw->title)->toStartWith('brng:')
        ->and($raw->title)->not->toContain('Netflix')
        ->and($raw->amount)->not->toContain('15.99')
        ->and($fresh->title)->toBe('Netflix Ünïcødé')
        ->and($fresh->amount)->toBe('15.99');
});

it('makes the ULID, household and key version before insert', function () {
    $note = new EncryptedNote(['title' => 'Spotify']);

    expect($note->id)->toBeString()->toHaveLength(26)
        ->and($note->household_id)->toBe(NOTES_A)
        ->and($note->key_version)->toBe(1);

    $note->save();

    expect(EncryptedNote::query()->findOrFail($note->id)->title)->toBe('Spotify');
});

it('refuses to save a field encrypted before household_id was set to another household', function () {
    $note = new EncryptedNote(['title' => 'Spotify', 'household_id' => NOTES_B]);

    expect(fn () => $note->save())->toThrow(RuntimeException::class, 'set household_id before encrypted fields')
        ->and(DB::table('encrypted_notes')->count())->toBe(0);

    // The other way round works.
    $ok = new EncryptedNote(['household_id' => NOTES_B, 'title' => 'Spotify']);
    $ok->save();
    inHousehold(NOTES_B);
    TestKeys::forget();

    expect(EncryptedNote::query()->findOrFail($ok->id)->title)->toBe('Spotify');
});

it('refuses to set a field on a row loaded without its household or key version', function (array $columns) {
    $note = EncryptedNote::create(['title' => 'Old', 'amount' => '1.00']);
    app(DataKeyRing::class)->createHousehold(NOTES_A);
    $partial = EncryptedNote::query()->select($columns)->findOrFail($note->id);

    expect(fn () => $partial->amount = '2.00')->toThrow(RuntimeException::class, 'before setting the encrypted field amount')
        ->and(EncryptedNote::query()->findOrFail($note->id)->title)->toBe('Old');
})->with([
    'without key_version' => [['id', 'household_id', 'title']],
    'without household_id' => [['id', 'key_version', 'title']],
]);

it('gives a row with only null fields a key version, whatever the trait order', function () {
    $note = EncryptedNoteTraitsReversed::create(['title' => null]);

    expect($note->household_id)->toBe(NOTES_A)
        ->and($note->key_version)->toBe(1);
});

it('refuses to encrypt without a household', function () {
    app()->forgetInstance(HouseholdScope::CONTAINER_KEY);

    expect(fn () => new EncryptedNote(['title' => 'Spotify']))->toThrow(RuntimeException::class, 'household_id');
});

it('keeps null as null', function () {
    $note = EncryptedNote::create(['title' => null]);

    expect(rawNote($note->id)->title)->toBeNull()
        ->and($note->fresh()?->title)->toBeNull();
});

describe('ciphertext bound to its row', function () {
    beforeEach(function () {
        $this->first = EncryptedNote::create(['title' => 'First', 'amount' => '1.00']);
        $this->second = EncryptedNote::create(['title' => 'Second', 'amount' => '2.00']);
    });

    it('fails when swapped between rows', function () {
        DB::table('encrypted_notes')->where('id', $this->second->id)->update(['title' => rawNote($this->first->id)->title]);

        expect(fn () => EncryptedNote::query()->findOrFail($this->second->id)->title)->toThrow(FieldDecryptionFailed::class);
    });

    it('fails when moved into another column', function () {
        DB::table('encrypted_notes')->where('id', $this->first->id)->update(['amount' => rawNote($this->first->id)->title]);

        expect(fn () => EncryptedNote::query()->findOrFail($this->first->id)->amount)->toThrow(FieldDecryptionFailed::class);
    });

    it('fails when the row is moved to another household', function () {
        DB::table('encrypted_notes')->where('id', $this->first->id)->update(['household_id' => NOTES_B]);
        inHousehold(NOTES_B);

        expect(fn () => EncryptedNote::query()->findOrFail($this->first->id)->title)->toThrow(FieldDecryptionFailed::class);
    });

    it('fails when altered', function () {
        $raw = rawNote($this->first->id)->title;
        $altered = substr($raw, 0, -2).($raw[-2] === 'A' ? 'B' : 'A').$raw[-1];
        DB::table('encrypted_notes')->where('id', $this->first->id)->update(['title' => $altered]);

        expect(fn () => EncryptedNote::query()->findOrFail($this->first->id)->title)->toThrow(FieldDecryptionFailed::class);
    });
});

describe('household key versions', function () {
    it('keeps a row on its version, and puts new rows on the newest', function () {
        $old = EncryptedNote::create(['title' => 'Old', 'amount' => '1.00']);
        app(DataKeyRing::class)->createHousehold(NOTES_A);
        TestKeys::forget();

        $old = EncryptedNote::query()->findOrFail($old->id);
        $old->update(['amount' => '2.00']);
        $new = EncryptedNote::create(['title' => 'New']);
        TestKeys::forget();

        expect($old->key_version)->toBe(1)
            ->and($new->key_version)->toBe(2)
            ->and(EncryptedNote::query()->findOrFail($old->id)->only(['title', 'amount']))->toBe(['title' => 'Old', 'amount' => '2.00'])
            ->and(EncryptedNote::query()->findOrFail($new->id)->title)->toBe('New');
    });

    it('fails when a row claims another version', function () {
        $note = EncryptedNote::create(['title' => 'Old']);
        app(DataKeyRing::class)->createHousehold(NOTES_A);
        DB::table('encrypted_notes')->where('id', $note->id)->update(['key_version' => 2]);

        expect(fn () => EncryptedNote::query()->findOrFail($note->id)->title)->toThrow(FieldDecryptionFailed::class);
    });
});

it('decrypts only the fields that are read', function () {
    $note = EncryptedNote::create(['title' => 'Lazy']);
    TestKeys::forget();
    app()->instance(MasterKeyLoader::class, new MasterKeyLoader(null, null));
    app()->forgetInstance(DataKeyRing::class);
    app()->forgetInstance(HouseholdCipher::class);

    // Loading and listing rows needs no key; reading a field does.
    $loaded = EncryptedNote::query()->findOrFail($note->id);

    expect($loaded->key_version)->toBe(1)
        ->and(fn () => $loaded->title)->toThrow(MasterKeyMissing::class);
});

it('keeps encrypted fields out of toArray() and toJson()', function () {
    $note = EncryptedNote::create(['title' => 'Hidden', 'amount' => '9.99']);

    expect($note->toArray())->not->toHaveKeys(['title', 'amount'])
        ->and($note->toJson())->not->toContain('Hidden')
        ->and($note->title)->toBe('Hidden');
});

it('writes ciphertext the stock BoringCrypto backend reads, and the other way round', function () {
    $key = new SymmetricKey(random_bytes(32));
    $fast = new FastBoringCrypto;
    $stock = new BoringCrypto;

    foreach (['', 'Ünïcødé', random_bytes(1000)] as $plaintext) {
        expect($stock->decrypt($fast->encrypt($plaintext, $key, 'aad'), $key, 'aad'))->toBe($plaintext)
            ->and($fast->decrypt($stock->encrypt($plaintext, $key, 'aad'), $key, 'aad'))->toBe($plaintext);
    }
});

it('leaves no plaintext in the database file, its WAL or the logs', function () {
    $root = sys_get_temp_dir().'/abonemen-cast-leak-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($root);
    touch("{$root}/database.sqlite");
    config()->set('database.connections.leak', ['driver' => 'sqlite', 'database' => "{$root}/database.sqlite", 'journal_mode' => 'wal', 'foreign_key_constraints' => true]);
    config()->set('database.default', 'leak');
    $this->artisan('migrate', ['--force' => true]);
    TestKeys::forget();
    TestKeys::installRecoveryKey();
    app(DataKeyRing::class)->createHousehold(NOTES_A);
    EncryptedNote::createTable();

    $title = Canary::text('encrypted_notes.title', 'Kanarie Ünïcødé 4d5e6f');
    $amount = Canary::amount('encrypted_notes.amount', 8462913);
    EncryptedNote::create(['title' => $title->value, 'amount' => $amount->value]);
    DB::purge('leak');

    try {
        expect((new LeakScanner([$title, $amount]))->scan([$root]))->toBe([]);
    } finally {
        config()->set('database.default', 'sqlite');
        File::deleteDirectory($root);
    }
});
