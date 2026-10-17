<?php

use App\Actions\Crypto\CreateRecoveryKey;
use App\Models\DataKey;
use App\Models\Setting;
use App\Support\Crypto\DataKeyRecovery;
use App\Support\Crypto\DataKeyRing;
use App\Support\Crypto\InvalidRecoverySecret;
use App\Support\Crypto\KeySeal;
use App\Support\Crypto\KeyUnwrapFailed;
use App\Support\Crypto\KeyWrap;
use App\Support\Crypto\MasterKeyLoader;
use App\Support\Crypto\RecoveryKeyMissing;
use App\Support\Crypto\RecoveryKeyStore;
use App\Support\Crypto\RecoverySecret;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Crypto\TestKeys;

/*
 * SEC-01, ACC-01: every data key is also sealed to the public half of the
 * recovery key, so the private half alone restores every key, including the
 * ones made after setup.
 */

uses(RefreshDatabase::class);

describe('recovery', function () {
    beforeEach(function () {
        $this->secret = TestKeys::installRecoveryKey();
        $this->ring = app(DataKeyRing::class);
    });

    it('restores every data key with only the private half', function () {
        $made = [
            'global' => $this->ring->createGlobal(),
            'a1' => $this->ring->createHousehold('01JAHOUSEHOLDAAAAAAAAAAAAA'),
            'a2' => $this->ring->createHousehold('01JAHOUSEHOLDAAAAAAAAAAAAA'),
            'b1' => $this->ring->createHousehold('01JBHOUSEHOLDBBBBBBBBBBBBB'),
        ];
        $typed = $this->secret->toDisplay();

        // The master key is gone, and nothing is left in memory.
        app()->instance(MasterKeyLoader::class, new MasterKeyLoader(null, null));
        TestKeys::forget();

        $recovered = app(DataKeyRecovery::class)->unsealAll(RecoverySecret::fromDisplay($typed));

        expect($recovered)->toHaveCount(4);
        $byOwner = DataKey::query()->get()->mapWithKeys(fn (DataKey $row): array => [
            "{$row->owner_id}:{$row->version}" => $recovered[$row->id]->bytes(),
        ]);
        expect($byOwner['instance:1'])->toBe($made['global']->bytes())
            ->and($byOwner['01JAHOUSEHOLDAAAAAAAAAAAAA:1'])->toBe($made['a1']->bytes())
            ->and($byOwner['01JAHOUSEHOLDAAAAAAAAAAAAA:2'])->toBe($made['a2']->bytes())
            ->and($byOwner['01JBHOUSEHOLDBBBBBBBBBBBBB:1'])->toBe($made['b1']->bytes());
    });

    it('fails with another recovery key', function () {
        $this->ring->createHousehold('01JAHOUSEHOLDAAAAAAAAAAAAA');

        expect(fn () => app(DataKeyRecovery::class)->unsealAll(RecoverySecret::generate()))
            ->toThrow(KeyUnwrapFailed::class, 'cannot be opened');
    });

    it('refuses a sealed key copied into another row', function () {
        $this->ring->createHousehold('01JAHOUSEHOLDAAAAAAAAAAAAA');
        $this->ring->createHousehold('01JBHOUSEHOLDBBBBBBBBBBBBB');
        $a = DataKey::query()->where('owner_id', '01JAHOUSEHOLDAAAAAAAAAAAAA')->sole();
        DataKey::query()->where('owner_id', '01JBHOUSEHOLDBBBBBBBBBBBBB')->update(['wrapped_rk' => $a->wrapped_rk]);

        expect(fn () => app(DataKeyRecovery::class)->unsealAll($this->secret))
            ->toThrow(KeyUnwrapFailed::class, 'belongs to another row');
    });

    it('stops at a key that was never sealed', function () {
        $this->ring->createHousehold('01JAHOUSEHOLDAAAAAAAAAAAAA');
        DataKey::query()->update(['wrapped_rk' => null]);

        expect(fn () => app(DataKeyRecovery::class)->unsealAll($this->secret))
            ->toThrow(KeyUnwrapFailed::class, 'never sealed');
    });

    it('stores only the sealed form', function () {
        $key = $this->ring->createHousehold('01JAHOUSEHOLDAAAAAAAAAAAAA');
        $row = DataKey::query()->sole();

        expect($row->wrapped_rk)->toStartWith('v1:')
            ->and(base64_decode(substr((string) $row->wrapped_rk, 3)))->not->toContain($key->bytes())
            ->and($row->toArray())->not->toHaveKey('wrapped_rk');
    });

    it('refuses an altered sealed key', function () {
        $sealed = KeySeal::seal($this->secret->publicKey(), random_bytes(32), 'bound');
        $raw = base64_decode(substr($sealed, 3));
        $raw[40] = chr(ord($raw[40]) ^ 1);

        expect(fn () => KeySeal::unseal($this->secret, 'v1:'.base64_encode($raw), 'bound'))->toThrow(KeyUnwrapFailed::class)
            ->and(fn () => KeySeal::unseal($this->secret, 'v2:'.substr($sealed, 3), 'bound'))->toThrow(KeyUnwrapFailed::class)
            ->and(fn () => KeySeal::unseal($this->secret, 'v1:***', 'bound'))->toThrow(KeyUnwrapFailed::class)
            ->and(strlen(KeySeal::unseal($this->secret, $sealed, 'bound')))->toBe(32);
    });
});

describe('without a recovery key', function () {
    it('makes no data key', function () {
        expect(fn () => app(DataKeyRing::class)->createHousehold('01JAHOUSEHOLDAAAAAAAAAAAAA'))->toThrow(RecoveryKeyMissing::class)
            ->and(DataKey::query()->count())->toBe(0);
    });
});

describe('setting up the recovery key', function () {
    it('stores the public half and hands back the private half once', function () {
        $typed = app(CreateRecoveryKey::class)();
        app(RecoveryKeyStore::class)->forget();

        expect(Setting::query()->find(RecoveryKeyStore::SETTING)?->is_secret)->toBeFalse()
            ->and(app(RecoveryKeyStore::class)->publicKey())->toBe(RecoverySecret::fromDisplay($typed)->publicKey())
            ->and(Setting::query()->pluck('value')->implode(' '))->not->toContain(str_replace('-', '', $typed));
    });

    it('refuses to replace an installed recovery key', function () {
        app(CreateRecoveryKey::class)();

        expect(fn () => app(CreateRecoveryKey::class)())->toThrow(LogicException::class, 'already installed');
    });

    it('refuses a public key of the wrong size', function () {
        expect(fn () => app(RecoveryKeyStore::class)->install('short'))->toThrow(LogicException::class);
    });
});

describe('the private half as typed', function () {
    beforeEach(function () {
        $this->secret = RecoverySecret::generate();
        $this->typed = $this->secret->toDisplay();
    });

    it('is 14 groups of 4 base32 characters', function () {
        expect($this->typed)->toMatch('/^[A-Z2-7]{4}(-[A-Z2-7]{4}){13}$/');
    });

    // Pinned, and checked against Python's base64.b32encode and hashlib.blake2b:
    // changing it would make printed recovery keys unreadable.
    it('encodes a known key to a known text, and back', function () {
        $known = (new ReflectionClass(RecoverySecret::class))->newInstanceWithoutConstructor();
        (fn () => $this->secretKey = implode('', array_map(chr(...), range(0, 31))))->call($known);
        $expected = 'AAAQ-EAYE-AUDA-OCAJ-BIFQ-YDIO-B4IB-CEQT-CQKR-MFYY-DENB-WHA5-DYPT-WOKU';

        expect($known->toDisplay())->toBe($expected)
            ->and(RecoverySecret::fromDisplay($expected)->publicKey())->toBe($known->publicKey());
    });

    it('reads back regardless of case, spaces and look-alike digits', function () {
        $sloppy = strtolower(str_replace('-', ' ', $this->typed));
        $sloppy = strtr($sloppy, ['o' => '0', 'i' => '1', 'b' => '8']);

        expect(RecoverySecret::fromDisplay($sloppy)->publicKey())->toBe($this->secret->publicKey());
    });

    it('catches a typo with its checksum', function () {
        $typo = $this->typed;
        $typo[5] = $typo[5] === 'A' ? 'B' : 'A';

        expect(fn () => RecoverySecret::fromDisplay($typo))->toThrow(InvalidRecoverySecret::class, 'typo');
    });

    it('rejects the wrong length or characters, without repeating the input', function (string $typed) {
        try {
            RecoverySecret::fromDisplay($typed);
            $this->fail('Expected InvalidRecoverySecret.');
        } catch (InvalidRecoverySecret $e) {
            expect($e->getMessage())->not->toContain($typed);
        }
    })->with([
        'too short' => ['ABCD-EFGH'],
        'not base32' => [str_repeat('#', 56)],
    ]);

    it('hides the key from dumps and refuses to serialise it', function () {
        expect(print_r($this->secret, true))->toContain('[redacted]')
            ->and(print_r($this->secret, true))->not->toContain(str_replace('-', '', $this->typed))
            ->and(fn () => serialize($this->secret))->toThrow(LogicException::class);
    });
});

it('binds the sealed key with the same associated data as the master-key wrap', function () {
    $secret = TestKeys::installRecoveryKey();
    app(DataKeyRing::class)->createHousehold('01JAHOUSEHOLDAAAAAAAAAAAAA');
    $row = DataKey::query()->sole();
    $aad = KeyWrap::associatedData($row->id, $row->owner_type, $row->owner_id, $row->version);

    expect(strlen(KeySeal::unseal($secret, (string) $row->wrapped_rk, $aad)))->toBe(32)
        ->and(fn () => KeySeal::unseal($secret, (string) $row->wrapped_rk, KeyWrap::associatedData($row->id, $row->owner_type, $row->owner_id, 2)))
        ->toThrow(KeyUnwrapFailed::class, 'belongs to another row');
});
