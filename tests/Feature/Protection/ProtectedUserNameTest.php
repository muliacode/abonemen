<?php

namespace Tests\Feature\Protection;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ProtectedUserNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_name_is_unreadable_in_the_raw_database_but_read_back_by_the_app(): void
    {
        $user = User::factory()->create(['name' => 'Sanne Visser']);

        $this->assertStringNotContainsString('Visser', (string) DB::table('users')->value('name'));
        $this->assertSame('Sanne Visser', $user->fresh()->name);
        $this->assertSame('SV', $user->fresh()->initials());
    }

    public function test_a_registered_name_is_protected_and_shown_on_the_profile_page(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Sanne Visser',
            'email' => 'sanne@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('Visser', (string) DB::table('users')->value('name'));

        $user = User::whereEmail('sanne@example.com')->sole();

        Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->assertSet('name', 'Sanne Visser');
    }

    public function test_changing_a_name_on_the_profile_page_is_protected_too(): void
    {
        $user = User::factory()->create(['name' => 'Sanne Visser']);

        Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->set('name', 'Sanne de Vries')
            ->set('email', $user->email)
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertStringNotContainsString('Vries', (string) DB::table('users')->value('name'));
        $this->assertSame('Sanne de Vries', $user->fresh()->name);
    }
}
