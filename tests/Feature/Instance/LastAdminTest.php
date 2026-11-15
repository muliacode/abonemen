<?php

namespace Tests\Feature\Instance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An instance always keeps somebody who can run it.
 */
class LastAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_last_instance_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->instanceAdmin()->create();

        Livewire::actingAs($admin)
            ->test('pages::settings.delete-user-modal')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasErrors('password');

        $this->assertModelExists($admin);
    }

    public function test_an_instance_admin_can_delete_their_account_while_another_admin_remains(): void
    {
        $admin = User::factory()->instanceAdmin()->create();
        User::factory()->instanceAdmin()->create();

        Livewire::actingAs($admin)
            ->test('pages::settings.delete-user-modal')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasNoErrors();

        $this->assertModelMissing($admin);
    }
}
