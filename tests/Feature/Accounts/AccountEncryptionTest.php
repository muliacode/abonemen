<?php

use App\Actions\Accounts\EmailAlreadyTaken;
use App\Actions\Accounts\FindUserByEmail;
use App\Actions\Crypto\CreateGlobalDataKey;
use App\Models\User;
use App\Support\Crypto\DataKeyMissing;
use App\Support\Crypto\DataKeyRing;
use App\Support\Crypto\GlobalEncrypter;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Crypto\TestKeys;

/*
 * SEC-01, SEC-03: account fields are encrypted under the global data key with
 * spatie/laravel-ciphersweet; the email is found through a 32-bit blind index,
 * and candidates are confirmed by decrypting them.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    TestKeys::installRecoveryKey();
    app(CreateGlobalDataKey::class)();

    $this->alice = User::create(['name' => 'Alice Ünïcødé', 'email' => 'Alice@Example.org', 'password' => 'correct horse']);
    $this->bob = User::create(['name' => 'Bob', 'email' => 'bob@example.org', 'password' => 'battery staple']);
    TestKeys::forget();
});

function rawUser(string $id): object
{
    return DB::table('users')->where('id', $id)->sole();
}

it('stores email and name as ciphertext and reads them back', function () {
    $raw = rawUser($this->alice->id);

    expect($raw->email)->toStartWith('brng:')
        ->and($raw->email)->not->toContain('Alice')
        ->and($raw->name)->not->toContain('Alice')
        ->and(User::query()->findOrFail($this->alice->id)->only(['name', 'email']))
        ->toBe(['name' => 'Alice Ünïcødé', 'email' => 'Alice@Example.org']);
});

it('keeps only a 32-bit blind index of the email', function () {
    $index = DB::table('blind_indexes')->where('indexable_id', $this->alice->id)->sole();

    expect($index->name)->toBe(User::EMAIL_INDEX)
        ->and(strlen(hex2bin($index->value)))->toBe(4)
        ->and($index->value)->not->toContain('alice');
});

describe('signing in by email', function () {
    it('works through the blind index, ignoring case and surrounding spaces', function () {
        expect(Auth::attempt(['email' => ' alice@example.ORG ', 'password' => 'correct horse']))->toBeTrue()
            ->and(Auth::id())->toBe($this->alice->id);
    });

    it('refuses a wrong password or an unknown email', function () {
        expect(Auth::attempt(['email' => 'alice@example.org', 'password' => 'wrong']))->toBeFalse()
            ->and(Auth::attempt(['email' => 'carol@example.org', 'password' => 'correct horse']))->toBeFalse()
            ->and(Auth::attempt(['email' => '', 'password' => 'correct horse']))->toBeFalse();
    });

    it('checks any other credential against the found user', function () {
        expect(Auth::validate(['email' => 'alice@example.org', 'id' => $this->bob->id, 'password' => 'correct horse']))->toBeFalse()
            ->and(Auth::validate(['email' => 'alice@example.org', 'id' => $this->alice->id, 'password' => 'correct horse']))->toBeTrue();
    });
});

describe('blind index collisions', function () {
    beforeEach(function () {
        // Give Bob Alice's index value, as a 32-bit collision would.
        $aliceIndex = DB::table('blind_indexes')->where('indexable_id', $this->alice->id)->value('value');
        DB::table('blind_indexes')->where('indexable_id', $this->bob->id)->update(['value' => $aliceIndex]);
    });

    it('are resolved by decrypting the candidates', function () {
        $candidates = User::query()->whereBlind('email', User::EMAIL_INDEX, 'alice@example.org')->get();

        expect($candidates)->toHaveCount(2)
            ->and(app(FindUserByEmail::class)('alice@example.org')?->id)->toBe($this->alice->id);
    });

    it('never sign in the colliding account', function () {
        $this->alice->delete();

        expect(app(FindUserByEmail::class)('alice@example.org'))->toBeNull()
            ->and(Auth::attempt(['email' => 'alice@example.org', 'password' => 'battery staple']))->toBeFalse();
    });
});

it('binds each field to its row', function () {
    DB::table('users')->where('id', $this->bob->id)->update(['email' => rawUser($this->alice->id)->email]);

    expect(fn () => User::query()->findOrFail($this->bob->id)->email)->toThrow(SodiumException::class);
});

it('keeps email and name out of toArray() and toJson()', function () {
    $user = User::query()->findOrFail($this->alice->id);

    expect($user->toArray())->not->toHaveKeys(['name', 'email', 'password'])
        ->and($user->toJson())->not->toContain('Alice');
});

it('updates the blind index when the email changes', function () {
    $user = User::query()->findOrFail($this->alice->id);
    $user->update(['email' => 'alice.new@example.org']);

    expect(app(FindUserByEmail::class)('alice.new@example.org')?->id)->toBe($this->alice->id)
        ->and(app(FindUserByEmail::class)('alice@example.org'))->toBeNull();
});

it('needs the global data key', function () {
    DB::table('data_keys')->delete();
    TestKeys::forget();

    expect(fn () => User::create(['name' => 'Carol', 'email' => 'carol@example.org', 'password' => 'x']))->toThrow(DataKeyMissing::class);
});

describe('the GDK encrypter for packages', function () {
    it('round-trips values under a key per purpose', function () {
        $twoFactor = new GlobalEncrypter('fortify-two-factor');
        $other = new GlobalEncrypter('something-else');
        $sealed = $twoFactor->encrypt(['secret' => 'JBSWY3DPEHPK3PXP']);

        expect($sealed)->not->toContain('JBSWY3DPEHPK3PXP')
            ->and($twoFactor->decrypt($sealed))->toBe(['secret' => 'JBSWY3DPEHPK3PXP'])
            ->and($twoFactor->decrypt($twoFactor->encrypt('plain', false), false))->toBe('plain')
            ->and($twoFactor->getKey())->not->toBe($other->getKey())
            ->and($twoFactor->getKey())->not->toBe(app(DataKeyRing::class)->global()->bytes())
            ->and(fn () => $other->decrypt($sealed))->toThrow(DecryptException::class);
    });

    it('does not unserialise objects', function () {
        $encrypter = new GlobalEncrypter('test');

        expect($encrypter->decrypt($encrypter->encrypt(new ArrayObject([1]))))->toBeInstanceOf(__PHP_Incomplete_Class::class);
    });
});

describe('unique email addresses', function () {
    it('refuses a second account with the same email, in any case or Unicode form', function (string $email) {
        expect(fn () => User::create(['name' => 'Copy', 'email' => $email, 'password' => 'x']))->toThrow(EmailAlreadyTaken::class)
            ->and(User::query()->count())->toBe(2);
    })->with([
        'same' => ['Alice@Example.org'],
        'other case' => ['ALICE@example.org'],
        'spaces' => [' alice@example.org '],
    ]);

    it('refuses changing an email to one another account has, and allows keeping your own', function () {
        $bob = User::query()->findOrFail($this->bob->id);

        expect(fn () => $bob->update(['email' => 'alice@example.org']))->toThrow(EmailAlreadyTaken::class);

        $alice = User::query()->findOrFail($this->alice->id);
        $alice->update(['email' => 'ALICE@example.org', 'name' => 'Alice']);

        expect(User::query()->findOrFail($this->alice->id)->email)->toBe('ALICE@example.org');
    });

    it('signs in nobody if two accounts ever share an email', function () {
        // A second account with Alice's email, saved without the check, as a
        // race between two sign-ups could leave it.
        $duplicate = new class extends User
        {
            protected $table = 'users';

            public function beforeEncrypting(): void {}

            public function getMorphClass(): string
            {
                return User::class;
            }
        };
        $duplicate->fill(['name' => 'Mallory', 'email' => 'alice@example.org', 'password' => 'correct horse'])->save();

        expect(User::query()->whereBlind('email', User::EMAIL_INDEX, 'alice@example.org')->count())->toBe(2)
            ->and(app(FindUserByEmail::class)('alice@example.org'))->toBeNull()
            ->and(Auth::attempt(['email' => 'alice@example.org', 'password' => 'correct horse']))->toBeFalse();
    });
});

it('finds an email whatever the case of non-ASCII letters, as the index ignores it too', function () {
    $user = User::create(['name' => 'Änne', 'email' => 'Änne@Example.org', 'password' => 'secret secret']);

    expect(app(FindUserByEmail::class)('änne@example.org')?->id)->toBe($user->id)
        ->and(app(FindUserByEmail::class)('ÄNNE@EXAMPLE.ORG')?->id)->toBe($user->id)
        // NFD: "A" followed by a combining diaeresis.
        ->and(app(FindUserByEmail::class)("A\u{0308}nne@example.org")?->id)->toBe($user->id);
});

it('creates no table that stores emails in plaintext', function () {
    expect(Schema::hasTable('password_reset_tokens'))->toBeFalse();
});
