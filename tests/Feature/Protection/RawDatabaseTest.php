<?php

namespace Tests\Feature\Protection;

use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RawDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dump_of_the_whole_database_holds_none_of_the_protected_plaintext(): void
    {
        Notification::fake();

        // A person registers, signs in, forgets their password and creates a Household.
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

        // Another household, with people who have no login and one who joins later.
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

        $tables = collect(DB::select("select name from sqlite_master where type = 'table'"))->pluck('name');

        $this->assertNotEmpty($tables);

        foreach ($tables as $table) {
            $dump = mb_strtolower(json_encode(DB::table($table)->get(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            foreach ($protected as $plaintext) {
                $this->assertStringNotContainsString(
                    mb_strtolower($plaintext),
                    $dump,
                    "\"{$plaintext}\" is readable in the raw table {$table}.",
                );
            }
        }

        // And the app still reads it all back.
        $this->assertSame('Daan Oosterhuis', Member::whereKey($household->members()->get()->firstWhere('name', 'Daan Oosterhuis')->id)->sole()->name);
        $this->assertSame('mark.dekker@example.org', $mark->fresh()->email);
        $this->assertSame('Sanne', $sanne->fresh()->name);
    }
}
