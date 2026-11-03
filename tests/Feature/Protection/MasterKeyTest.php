<?php

namespace Tests\Feature\Protection;

use App\Models\Household;
use App\Models\Member;
use App\Models\User;
use App\Protection\ProtectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\ViewException;
use Livewire\Livewire;
use Tests\TestCase;

class MasterKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_protecting_data_without_a_master_key_fails_clearly(): void
    {
        $this->actingAs(User::factory()->create());

        config(['protection.master_key' => null]);

        $this->expectException(ProtectionException::class);
        $this->expectExceptionMessage('The master key is not set');

        Livewire::test('create-household-modal')
            ->set('name', 'Home')
            ->call('createHousehold');
    }

    public function test_a_malformed_master_key_is_rejected_clearly(): void
    {
        foreach (['not-a-key', 'base64:!!!', 'base64:'.base64_encode('too short')] as $malformed) {
            config(['protection.master_key' => $malformed]);

            try {
                Household::factory()->create();
                $this->fail("The key {$malformed} should have been rejected.");
            } catch (ProtectionException $exception) {
                $this->assertStringContainsString('not valid', $exception->getMessage());
            }
        }
    }

    public function test_reading_data_with_the_wrong_master_key_fails_instead_of_returning_garbage(): void
    {
        $household = Household::factory()->create(['name' => 'Home']);

        config(['protection.master_key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->expectException(ProtectionException::class);
        $this->expectExceptionMessage('does not match');

        Household::find($household->id)->name;
    }

    public function test_a_page_showing_protected_data_fails_with_the_wrong_master_key(): void
    {
        $user = User::factory()->create();
        Household::factory()->withUser($user)->create(['name' => 'Home']);

        config(['protection.master_key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->withoutExceptionHandling();
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('does not match');

        $this->actingAs($user)->get(route('households.index'));
    }

    public function test_reading_a_name_from_a_household_loaded_without_its_key_fails_clearly(): void
    {
        $household = Household::factory()->create(['name' => 'Home']);

        $this->expectException(ProtectionException::class);
        $this->expectExceptionMessage('data key was not loaded');

        Household::query()->select('id', 'name')->find($household->id)->name;
    }

    public function test_protecting_data_for_a_household_that_does_not_exist_fails_clearly(): void
    {
        $this->expectException(ProtectionException::class);
        $this->expectExceptionMessage('no Household with id 9999');

        Member::make(['household_id' => 9999, 'name' => 'Nobody']);
    }

    public function test_the_generated_master_key_is_one_the_app_accepts(): void
    {
        $key = $this->generatedKey();

        $this->assertMatchesRegularExpression('/^base64:[A-Za-z0-9+\/]{43}=$/', $key);

        config(['protection.master_key' => $key]);

        $this->assertSame('Home', Household::factory()->create(['name' => 'Home'])->fresh()->name);
    }

    private function generatedKey(): string
    {
        $this->assertSame(0, Artisan::call('master-key:generate'));

        return trim(Artisan::output());
    }
}
