<?php

namespace Tests\Feature\Instance;

use App\Support\MailConfiguration;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The app applies saved mail settings every time it starts, and that must never stop it from starting: a fresh
 * install runs artisan commands before the first migration, when there is no table, or no database file yet.
 * (No RefreshDatabase here: these tests are about the database not being ready.)
 */
class MailBootTest extends TestCase
{
    public function test_starting_without_the_settings_table_leaves_the_environment_in_charge(): void
    {
        $fromEnvironment = config('mail.default');

        (new MailConfiguration(new PlatformSettings))->apply();

        $this->assertSame($fromEnvironment, config('mail.default'));
    }

    public function test_starting_without_a_database_file_leaves_the_environment_in_charge(): void
    {
        $fromEnvironment = config('mail.default');

        config(['database.connections.sqlite.database' => '/nonexistent/abonemen.sqlite']);
        DB::purge();

        (new MailConfiguration(new PlatformSettings))->apply();

        $this->assertSame($fromEnvironment, config('mail.default'));
    }
}
