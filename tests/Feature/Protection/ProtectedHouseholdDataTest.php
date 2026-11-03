<?php

namespace Tests\Feature\Protection;

use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use App\Protection\ProtectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ProtectedHouseholdDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_household_name_is_unreadable_in_the_raw_database_but_shown_in_the_app(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test('create-household-modal')
            ->set('name', 'Home')
            ->set('mainCurrency', 'EUR')
            ->set('timezone', 'Europe/Amsterdam')
            ->call('createHousehold')
            ->assertHasNoErrors();

        $this->assertStringNotContainsString('Home', (string) DB::table('households')->value('name'));

        $this->get(route('households.index'))->assertOk()->assertSee('Home');
    }

    public function test_a_member_name_is_unreadable_in_the_raw_database_but_read_back_by_the_app(): void
    {
        $user = User::factory()->create(['name' => 'Sanne']);

        $household = Household::factory()->withUser($user)->create();

        $this->assertStringNotContainsString('Sanne', (string) DB::table('members')->value('name'));
        $this->assertSame('Sanne', $household->members()->sole()->name);
    }

    public function test_each_household_has_its_own_key(): void
    {
        $first = Household::factory()->create(['name' => 'Home']);
        $second = Household::factory()->create(['name' => 'Home']);

        $this->assertNotSame(
            DB::table('households')->where('id', $first->id)->value('data_key'),
            DB::table('households')->where('id', $second->id)->value('data_key'),
        );
        $this->assertNotSame(
            DB::table('households')->where('id', $first->id)->value('name'),
            DB::table('households')->where('id', $second->id)->value('name'),
        );
    }

    public function test_protected_data_copied_into_another_household_cannot_be_read_there(): void
    {
        $first = Household::factory()->create();
        $second = Household::factory()->create();
        $first->members()->create(['name' => 'Sanne']);
        $stranger = $second->members()->create(['name' => 'Daan']);

        // Someone with database access copies Sanne's stored name over Daan's.
        DB::table('members')->where('id', $stranger->id)->update([
            'name' => DB::table('members')->where('household_id', $first->id)->value('name'),
        ]);

        $this->expectException(ProtectionException::class);

        Member::find($stranger->id)->name;
    }
}
