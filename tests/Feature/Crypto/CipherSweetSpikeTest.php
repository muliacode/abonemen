<?php

use App\Models\Scopes\HouseholdScope;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Crypto\FastBoringCrypto;
use App\Support\Crypto\GlobalEncrypter;
use App\Support\Crypto\HouseholdCipher;
use App\Support\Crypto\HouseholdKeyRing;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use ParagonIE\CipherSweet\Backend\BoringCrypto;
use ParagonIE\CipherSweet\Backend\Key\SymmetricKey;
use PragmaRX\Google2FA\Google2FA;

/*
 * SPIKE (#12): CipherSweet multi-tenant encryption with per-household keys.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->householdA = (string) Str::ulid();
    $this->householdB = (string) Str::ulid();

    $ring = app(HouseholdKeyRing::class);
    $ring->add($this->householdA, random_bytes(32));
    $ring->add($this->householdB, random_bytes(32));

    app()->instance(HouseholdScope::CONTAINER_KEY, $this->householdA);
});

function makeSubscription(string $name, ?string $household = null): Subscription
{
    return Subscription::create([
        'household_id' => $household ?? test()->householdA,
        'name' => $name,
        'price' => '1299',
        'currency' => 'EUR',
        'notes' => 'Family plan',
        'url' => 'https://example.com/account',
        'billing_cycle' => 'monthly',
        'next_payment_on' => '2026-11-01',
    ]);
}

it('stores household fields as ciphertext and reads them back', function () {
    $subscription = makeSubscription('Streamflix');

    $raw = DB::table('subscriptions')->where('id', $subscription->id)->first();

    expect($raw->name)->not->toContain('Streamflix')
        ->and($raw->notes)->not->toContain('Family')
        ->and(Subscription::find($subscription->id)->name)->toBe('Streamflix')
        ->and(Subscription::find($subscription->id)->price)->toBe('1299');
});

it('assigns the ULID before encrypting, whatever the attribute order', function () {
    $subscription = new Subscription;
    $subscription->name = 'Ordered first';
    $subscription->fill(['price' => '100', 'currency' => 'EUR', 'billing_cycle' => 'monthly', 'next_payment_on' => '2026-11-01']);
    $subscription->save();

    expect(Subscription::find($subscription->id)->name)->toBe('Ordered first');
});

it('fails to decrypt a ciphertext copied to another row of the same household', function () {
    $first = makeSubscription('First');
    $second = makeSubscription('Second');

    $stolen = DB::table('subscriptions')->where('id', $first->id)->value('name');
    DB::table('subscriptions')->where('id', $second->id)->update(['name' => $stolen]);

    expect(fn () => Subscription::find($second->id)->name)->toThrow(SodiumException::class);
});

it('fails to decrypt a row moved to another household', function () {
    $subscription = makeSubscription('Moved');

    DB::table('subscriptions')->where('id', $subscription->id)->update(['household_id' => $this->householdB]);
    app()->instance(HouseholdScope::CONTAINER_KEY, $this->householdB);

    expect(fn () => Subscription::find($subscription->id)->name)->toThrow(SodiumException::class);
});

it('fails to decrypt once the household key is gone (crypto-shredding)', function () {
    $subscription = makeSubscription('Shredded');

    app(HouseholdKeyRing::class)->forget($this->householdA);
    app(HouseholdCipher::class)->flush($this->householdA);

    expect(fn () => Subscription::find($subscription->id)->name)->toThrow(RuntimeException::class);
});

it('derives a different key per household and per column', function () {
    $cipher = app(HouseholdCipher::class);
    $row = (string) Str::ulid();
    $ciphertext = $cipher->encrypt('secret', $this->householdA, $row, 'subscriptions', 'name');

    expect($cipher->decrypt($ciphertext, $this->householdA, $row, 'subscriptions', 'name'))->toBe('secret')
        ->and(fn () => $cipher->decrypt($ciphertext, $this->householdA, $row, 'subscriptions', 'notes'))->toThrow(SodiumException::class)
        ->and(fn () => $cipher->decrypt($ciphertext, $this->householdB, $row, 'subscriptions', 'name'))->toThrow(SodiumException::class);
});

it('encrypts user email and name under the GDK and finds users by blind index', function () {
    $user = User::create(['name' => 'Kanarie Vogel', 'email' => 'Kanarie@Example.com', 'password' => 'secret-password']);

    $raw = DB::table('users')->where('id', $user->id)->first();
    $index = DB::table('blind_indexes')->where('indexable_id', $user->id)->first();

    expect($raw->email)->not->toContain('anarie')
        ->and($raw->name)->not->toContain('Vogel')
        ->and(strlen(hex2bin($index->value)) * 8)->toBe(User::BLIND_INDEX_BITS)
        ->and(User::whereBlind('email', 'email_index', 'kanarie@example.com')->first()?->is($user))->toBeTrue()
        ->and(User::find($user->id)->email)->toBe('Kanarie@Example.com');
});

it('fails to decrypt a user email copied to another user', function () {
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'secret-password']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'secret-password']);

    DB::table('users')->where('id', $bob->id)->update([
        'email' => DB::table('users')->where('id', $alice->id)->value('email'),
    ]);

    expect(fn () => User::find($bob->id))->toThrow(SodiumException::class);
});

it('stores the Fortify TOTP secret and recovery codes under the GDK, not APP_KEY', function () {
    $user = User::create(['name' => 'Totp', 'email' => 'totp@example.com', 'password' => 'secret-password']);

    app(EnableTwoFactorAuthentication::class)($user);
    $user->refresh();

    $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    expect(Fortify::currentEncrypter())->toBeInstanceOf(GlobalEncrypter::class)
        ->and(fn () => Crypt::decrypt($user->two_factor_secret))->toThrow(DecryptException::class)
        ->and(app(TwoFactorAuthenticationProvider::class)->verify($secret, $code))->toBeTrue()
        ->and($user->recoveryCodes())->toHaveCount(8);
});

it('keeps FastBoringCrypto interchangeable with CipherSweet BoringCrypto', function (string $plaintext, string $aad) {
    $stock = new BoringCrypto;
    $fast = new FastBoringCrypto;
    $key = new SymmetricKey(random_bytes(32));

    expect($fast->decrypt($stock->encrypt($plaintext, $key, $aad), $key, $aad))->toBe($plaintext)
        ->and($stock->decrypt($fast->encrypt($plaintext, $key, $aad), $key, $aad))->toBe($plaintext)
        ->and(fn () => $fast->decrypt($stock->encrypt($plaintext, $key, $aad), $key, $aad.'x'))->toThrow(SodiumException::class);
})->with([
    'empty' => ['', ''],
    'short' => ['Streamflix', 'row:1'],
    'unicode' => [str_repeat('é€', 100), 'row:2'],
    'binary' => [random_bytes(1000), random_bytes(64)],
]);
