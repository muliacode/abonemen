<?php

namespace Tests\Support\Leaks;

use App\Models\User;

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
    public const PLAINTEXT_UNTIL_FIXED = [
        'users.name' => '#22',
        'users.email' => '#22',
    ];

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

        return $canaries;
    }
}
