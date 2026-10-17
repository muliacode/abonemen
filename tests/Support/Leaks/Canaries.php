<?php

namespace Tests\Support\Leaks;

use App\Actions\Crypto\CreateHouseholdDataKey;
use App\Models\User;
use App\Support\Crypto\FileVault;

/**
 * Writes a canary to every sensitive field the app has so far, through the
 * same code a request would use.
 *
 * When a feature adds a sensitive field (see "Fields" in
 * docs/product/technical-design.md), it adds a canary here in the same pull
 * request. Household fields use the Encrypted cast, which has its own leak test
 * (EncryptedCastTest); each household model adds its canaries here as it lands.
 * Backups (#85) join the scan when they exist.
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

        $household = '01JLEAKTESTHOUSEHOLD000000';
        app(CreateHouseholdDataKey::class)($household);
        $logo = Canary::text('files.contents', '<svg><title>Kanarie logo 2b8e4f</title></svg>');
        app(FileVault::class)->store($household, $logo->value, 'image/svg+xml');
        $canaries[] = $logo;

        return $canaries;
    }
}
