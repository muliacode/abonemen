<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Choices the Instance Admin makes for the whole instance, kept in the database so that they can change without a deploy.
 */
class PlatformSettings
{
    private const REGISTRATION = 'registration_open';

    /**
     * Whether anyone can register. Closed until an Instance Admin opens it.
     */
    public function registrationIsOpen(): bool
    {
        return DB::table('platform_settings')->where('key', self::REGISTRATION)->value('value') === '1';
    }

    public function openRegistration(bool $open): void
    {
        DB::table('platform_settings')->upsert(
            [['key' => self::REGISTRATION, 'value' => $open ? '1' : '0', 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_at'],
        );
    }
}
