<?php

use App\Actions\Crypto\CreateGlobalDataKey;
use App\Actions\Crypto\CreateHouseholdDataKey;
use App\Models\DataKey;
use App\Support\Crypto\DataKeyMaterial;
use App\Support\Crypto\DataKeyMissing;
use App\Support\Crypto\DataKeyRing;
use App\Support\Crypto\KeyUnwrapFailed;
use App\Support\Crypto\KeyWrap;
use App\Support\Crypto\MasterKey;
use App\Support\Crypto\MasterKeyLoader;
use App\Support\Crypto\MasterKeyMissing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Crypto\TestKeys;

/*
 * SEC-01: the global data key and versioned household data keys live in
 * data_keys, wrapped by the master key with XChaCha20-Poly1305. The key's id,
 * owner and version are the associated data.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    TestKeys::installRecoveryKey();
});

const HOUSEHOLD_A = '01JAHOUSEHOLDAAAAAAAAAAAAA';
const HOUSEHOLD_B = '01JBHOUSEHOLDBBBBBBBBBBBBB';

function bytesFrom(int $first, int $count): string
{
    return implode('', array_map(chr(...), range($first, $first + $count - 1)));
}

function freshRing(): DataKeyRing
{
    TestKeys::forget();

    return app(DataKeyRing::class);
}

describe('known-answer vectors', function () {
    it('uses XChaCha20-Poly1305 as specified (draft-irtf-cfrg-xchacha, A.3.1)', function () {
        $plaintext = "Ladies and Gentlemen of the class of '99: If I could offer you only one tip for the future, sunscreen would be it.";

        $sealed = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            hex2bin('50515253c0c1c2c3c4c5c6c7'),
            hex2bin('404142434445464748494a4b4c4d4e4f5051525354555657'),
            hex2bin('808182838485868788898a8b8c8d8e8f909192939495969798999a9b9c9d9e9f'),
        );

        expect(bin2hex($sealed))->toBe(
            'bd6d179d3e83d43b9576579493c0e939572a1700252bfaccbed2902c21396cbb731c7f1b0b4aa6440bf3a82f4eda7e39ae64c6708c54c216cb96b72e1213b4522f8c9ba40db5d945b11b69b982c1bb9e3f3fac2bc369488f76b2383565d3fff921f9664c97637da9768812f615c68b13b52e'
            .'c0875924c1c7987947deafd8780acf49',
        );
    });

    // Pinned values: changing them breaks every stored key. They were checked
    // against an independent XChaCha20-Poly1305 implementation (HChaCha20 plus
    // ChaCha20-Poly1305 from Python's cryptography package).
    it('encodes the associated data as length-prefixed parts', function () {
        $aad = KeyWrap::associatedData('01JAAAAAAAAAAAAAAAAAAAAAAA', 'household', '01JBBBBBBBBBBBBBBBBBBBBBBB', 1);

        expect(bin2hex($aad))->toBe(
            '0000001161626f6e656d656e3a646174612d6b6579'
            .'0000001a30314a4141414141414141414141414141414141414141414141'
            .'00000009686f757365686f6c64'
            .'0000001a30314a4242424242424242424242424242424242424242424242'
            .'0000000131',
        );
    });

    it('wraps a known key to a known value, and back', function () {
        $masterKey = MasterKey::fromBase64(base64_encode(bytesFrom(0, 32)));
        $aad = KeyWrap::associatedData('01JAAAAAAAAAAAAAAAAAAAAAAA', 'household', '01JBBBBBBBBBBBBBBBBBBBBBBB', 1);
        $expected = 'v1:QEFCQ0RFRkdISUpLTE1OT1BRUlNUVVZX9BgnU/TFXzGn3a2Vg7FLvaKLn/cnbGWtUgjHfjU5Ha/2GJy8p286bPR+0joPfFxy';

        expect(KeyWrap::wrap($masterKey, bytesFrom(32, 32), $aad, bytesFrom(64, 24)))->toBe($expected)
            ->and(KeyWrap::unwrap($masterKey, $expected, $aad))->toBe(bytesFrom(32, 32));
    });

    it('gives different associated data for tuples that would join to the same string', function () {
        expect(KeyWrap::associatedData('ab', 'c', 'd', 1))
            ->not->toBe(KeyWrap::associatedData('a', 'bc', 'd', 1));
    });
});

describe('the key ring', function () {
    it('creates household keys in versions and unwraps each of them', function () {
        $ring = freshRing();
        $first = $ring->createHousehold(HOUSEHOLD_A);
        $second = $ring->createHousehold(HOUSEHOLD_A);

        $ring = freshRing();

        expect([$first->version, $second->version])->toBe([1, 2])
            ->and($ring->household(HOUSEHOLD_A)->version)->toBe(2)
            ->and($ring->household(HOUSEHOLD_A)->bytes())->toBe($second->bytes())
            ->and($ring->household(HOUSEHOLD_A, 1)->bytes())->toBe($first->bytes())
            ->and($first->bytes())->not->toBe($second->bytes());
    });

    it('gives each household its own key', function () {
        $ring = freshRing();

        expect($ring->createHousehold(HOUSEHOLD_A)->bytes())
            ->not->toBe($ring->createHousehold(HOUSEHOLD_B)->bytes())
            ->and($ring->household(HOUSEHOLD_B)->version)->toBe(1);
    });

    it('creates the global key once', function () {
        $created = app(CreateGlobalDataKey::class)();
        $again = app(CreateGlobalDataKey::class)();
        $global = freshRing()->global();

        expect($created)->toBeTrue()
            ->and($again)->toBeFalse()
            ->and(DataKey::query()->where('owner_type', DataKey::OWNER_GLOBAL)->count())->toBe(1)
            ->and($global->version)->toBe(1)
            ->and(fn () => app(DataKeyRing::class)->createGlobal())->toThrow(LogicException::class);
    });

    it('lets the Accounts domain create a household key through an action', function () {
        expect(app(CreateHouseholdDataKey::class)(HOUSEHOLD_A))->toBe(1)
            ->and(freshRing()->household(HOUSEHOLD_A)->version)->toBe(1);
    });

    it('says when a key does not exist', function () {
        expect(fn () => freshRing()->household(HOUSEHOLD_A))->toThrow(DataKeyMissing::class)
            ->and(fn () => freshRing()->global())->toThrow(DataKeyMissing::class);

        freshRing()->createHousehold(HOUSEHOLD_A);

        expect(fn () => freshRing()->household(HOUSEHOLD_A, 2))->toThrow(DataKeyMissing::class);
    });

    it('stores only the wrapped key', function () {
        $key = freshRing()->createHousehold(HOUSEHOLD_A);
        $row = DataKey::query()->sole();

        expect($row->wrapped_mk)->toStartWith('v1:')
            ->and($row->wrapped_mk)->not->toContain(base64_encode($key->bytes()))
            ->and(base64_decode(substr($row->wrapped_mk, 3)))->not->toContain($key->bytes())
            ->and($row->toArray())->not->toHaveKey('wrapped_mk');
    });

    it('needs the master key', function () {
        app()->instance(MasterKeyLoader::class, new MasterKeyLoader(null, null));
        app()->forgetInstance(DataKeyRing::class);

        expect(fn () => app(DataKeyRing::class)->createHousehold(HOUSEHOLD_A))->toThrow(MasterKeyMissing::class);
    });

    it('hides unwrapped keys from dumps and refuses to serialise them', function () {
        $key = freshRing()->createHousehold(HOUSEHOLD_A);

        expect(print_r($key, true))->toContain('[redacted]')
            ->and(print_r($key, true))->not->toContain(base64_encode($key->bytes()))
            ->and(fn () => serialize($key))->toThrow(LogicException::class)
            ->and(fn () => new DataKeyMaterial('too short', 1))->toThrow(LogicException::class);
    });
});

describe('a wrapped key bound to its row', function () {
    beforeEach(function () {
        freshRing()->createHousehold(HOUSEHOLD_A);
        $this->row = DataKey::query()->sole();
    });

    it('fails to unwrap when moved to another household', function () {
        $this->row->update(['owner_id' => HOUSEHOLD_B]);

        expect(fn () => freshRing()->household(HOUSEHOLD_B))->toThrow(KeyUnwrapFailed::class);
    });

    it('fails to unwrap when its version is changed', function () {
        $this->row->update(['version' => 7]);

        expect(fn () => freshRing()->household(HOUSEHOLD_A, 7))->toThrow(KeyUnwrapFailed::class);
    });

    it('fails to unwrap when copied into another row', function () {
        freshRing()->createHousehold(HOUSEHOLD_B);
        DataKey::query()->where('owner_id', HOUSEHOLD_B)->update(['wrapped_mk' => $this->row->wrapped_mk]);

        expect(fn () => freshRing()->household(HOUSEHOLD_B))->toThrow(KeyUnwrapFailed::class);
    });

    it('fails to unwrap when made global', function () {
        $this->row->update(['owner_type' => DataKey::OWNER_GLOBAL, 'owner_id' => DataKey::INSTANCE]);

        expect(fn () => freshRing()->global())->toThrow(KeyUnwrapFailed::class);
    });

    it('fails to unwrap with another master key', function () {
        app()->instance(MasterKeyLoader::class, new MasterKeyLoader(MasterKey::generate()->toBase64(), null));
        app()->forgetInstance(DataKeyRing::class);

        expect(fn () => app(DataKeyRing::class)->household(HOUSEHOLD_A))->toThrow(KeyUnwrapFailed::class);
    });

    it('fails to unwrap when altered', function (Closure $alter) {
        $this->row->update(['wrapped_mk' => $alter($this->row->wrapped_mk)]);

        expect(fn () => freshRing()->household(HOUSEHOLD_A))->toThrow(KeyUnwrapFailed::class);
    })->with([
        'one bit flipped' => [function (string $wrapped): string {
            $raw = base64_decode(substr($wrapped, 3));
            $raw[30] = chr(ord($raw[30]) ^ 1);

            return 'v1:'.base64_encode($raw);
        }],
        'truncated' => [fn (string $wrapped): string => substr($wrapped, 0, 20)],
        'unknown format' => [fn (string $wrapped): string => 'v2:'.substr($wrapped, 3)],
        'not base64' => [fn (string $wrapped): string => 'v1:***'],
    ]);
});
