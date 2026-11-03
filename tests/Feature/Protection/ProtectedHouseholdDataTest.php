<?php

namespace Tests\Feature\Protection;

use App\Exceptions\ProtectionException;
use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\QueryException;
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

    public function test_saving_without_model_events_never_stores_a_protected_value_in_plaintext(): void
    {
        $household = Household::factory()->create();

        // The name is set before the household is known, and the saving hook that would seal it is skipped.
        $member = new Member;
        $member->name = 'Waiting Person';
        $member->household_id = $household->id;

        try {
            $member->saveQuietly();
        } catch (QueryException) {
            // Failing closed is fine: the point is that nothing readable reaches the table.
        }

        $this->assertStringNotContainsString('Waiting Person', json_encode(DB::table('members')->get()));
    }

    public function test_a_member_created_through_its_household_relation_is_sealed_when_saved(): void
    {
        $household = Household::factory()->create();

        $household->members()->create(['name' => 'Waiting Person']);

        $this->assertStringNotContainsString('Waiting Person', (string) DB::table('members')->value('name'));
        $this->assertSame('Waiting Person', $household->members()->sole()->name);
    }

    public function test_a_member_still_waiting_for_its_household_reads_back_what_was_set(): void
    {
        $member = new Member;
        $member->name = 'Waiting Person';

        $this->assertSame('Waiting Person', $member->name);
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
