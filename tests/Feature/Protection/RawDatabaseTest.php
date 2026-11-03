<?php

namespace Tests\Feature\Protection;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class RawDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dump_of_the_whole_database_holds_none_of_the_protected_plaintext(): void
    {
        Notification::fake();

        // Sessions are stored in the database, as on a default install.
        config(['session.driver' => 'database']);

        // A User registers, forgets their password and creates a Household.
        $this->post(route('register.store'), [
            'name' => 'Sanne',
            'email' => 'sanne.visser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $sanne = User::whereEmail('sanne.visser@example.com')->sole();

        $this->post(route('password.email'), ['email' => 'sanne.visser@example.com']);

        Livewire::test('create-household-modal')
            ->set('name', 'Villa Zonnebloem')
            ->set('mainCurrency', 'EUR')
            ->set('timezone', 'Europe/Amsterdam')
            ->call('createHousehold')
            ->assertHasNoErrors();

        // Members without a sign-in, and another Household with its own User.
        $household = Household::sole();
        $household->members()->create(['name' => 'Daan Oosterhuis']);
        $household->members()->create(['name' => 'Kleine Fenna']);
        $mark = User::factory()->create(['name' => 'Mark', 'email' => 'mark.dekker@example.org']);
        $other = Household::factory()->withUser($mark)->create(['name' => 'Appartement Dekker']);
        $other->members()->create(['name' => 'Ayu Wulandari']);

        $protected = [
            'sanne.visser', 'sanne.visser@example.com', 'mark.dekker', 'mark.dekker@example.org', 'example.com', 'example.org',
            'Villa Zonnebloem', 'Zonnebloem', 'Appartement Dekker',
            'Daan Oosterhuis', 'Oosterhuis', 'Kleine Fenna', 'Ayu Wulandari', 'Wulandari',
        ];

        $tables = array_column(Schema::getTables(), 'name');

        $this->assertContains('sessions', $tables);
        $this->assertNotEmpty(DB::table('sessions')->get(), 'The scenario should have stored a session.');

        foreach ($tables as $table) {
            $rows = DB::table($table)->get()->map(function ($row) use ($table) {
                // Stored sessions are only base64 encoded, which anyone can undo.
                return $table === 'sessions' ? (object) [...(array) $row, 'payload' => base64_decode($row->payload)] : $row;
            });

            $dump = mb_strtolower(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            foreach ($protected as $plaintext) {
                $this->assertStringNotContainsString(
                    mb_strtolower($plaintext),
                    $dump,
                    "\"{$plaintext}\" is readable in the raw table {$table}.",
                );
            }
        }

        // And the app still reads it all back.
        $this->assertEqualsCanonicalizing(
            ['Sanne', 'Daan Oosterhuis', 'Kleine Fenna'],
            $household->members()->get()->map->name->all(),
        );
        $this->assertSame('mark.dekker@example.org', $mark->fresh()->email);
        $this->assertSame('sanne.visser@example.com', $sanne->fresh()->email);
    }
}
