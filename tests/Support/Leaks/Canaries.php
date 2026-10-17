<?php

namespace Tests\Support\Leaks;

use App\Models\Scopes\HouseholdScope;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Crypto\HouseholdKeyRing;
use Illuminate\Support\Str;

/**
 * Writes a canary to every sensitive field the app has so far, through the
 * same code a request would use.
 *
 * When a feature adds a sensitive field (see "Fields" in
 * docs/product/technical-design.md), it adds a canary here in the same pull
 * request. Uploads (#23) and backups (#85) join the scan when they exist.
 */
final class Canaries
{
    /**
     * Fields that are still stored in plaintext, with the issue that encrypts
     * them. The leak test accepts these leaks, and fails as soon as one stops
     * leaking, so the entry has to be removed with the fix.
     */
    public const PLAINTEXT_UNTIL_FIXED = [];

    /**
     * @return list<Canary>
     */
    public static function seed(): array
    {
        $canaries = [];

        $name = Canary::text('users.name', 'Kanarie Ünïcødé 7f3a9c');
        $email = Canary::text('users.email', 'kanarie-7f3a9c@leak-test.example');
        User::create([
            'name' => $name->value,
            'email' => $email->value,
            'password' => 'canary-password-7f3a9c',
        ]);
        array_push($canaries, $name, $email);

        // SPIKE (#12): household fields through the Encrypted cast.
        $household = (string) Str::ulid();
        app(HouseholdKeyRing::class)->add($household, random_bytes(32));
        app()->instance(HouseholdScope::CONTAINER_KEY, $household);

        $subscriptionName = Canary::text('subscriptions.name', 'Kanarie Streaming 7f3a9c');
        $price = Canary::amount('subscriptions.price', 7391483);
        $notes = Canary::text('subscriptions.notes', 'Kanarie notitie 7f3a9c');
        $url = Canary::text('subscriptions.url', 'https://kanarie-7f3a9c.example/account');
        Subscription::create([
            'name' => $subscriptionName->value,
            'price' => '7391483',
            'currency' => 'EUR',
            'notes' => $notes->value,
            'url' => $url->value,
            'billing_cycle' => 'monthly',
            'next_payment_on' => '2026-11-01',
        ]);
        array_push($canaries, $subscriptionName, $price, $notes, $url);

        return $canaries;
    }
}
