<?php

namespace Tests\Feature\Instance;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An Instance Admin finds a User by the exact email address, never by browsing or guessing a part of it,
 * and learns nothing about the Households that User belongs to.
 */
class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->instanceAdmin()->create();
        $this->actingAs($this->admin);
    }

    public function test_an_exact_email_address_finds_the_user_whatever_its_case(): void
    {
        $sanne = User::factory()->withHousehold()->create(['name' => 'Sanne Visser', 'email' => 'sanne.visser@example.com']);
        $sanne->currentHousehold->update(['name' => 'Villa Zonnebloem']);

        Livewire::test('pages::admin.users')
            ->set('email', 'Sanne.Visser@Example.com')
            ->call('find')
            ->assertSee('Sanne Visser')
            ->assertSee('sanne.visser@example.com')
            ->assertDontSee('Villa Zonnebloem');
    }

    public function test_a_part_of_an_email_address_finds_nobody(): void
    {
        User::factory()->create(['name' => 'Sanne Visser', 'email' => 'sanne.visser@example.com']);

        foreach (['sanne', 'sanne.visser', '@example.com', 'sanne.visser@example'] as $part) {
            Livewire::test('pages::admin.users')
                ->set('email', $part)
                ->call('find')
                ->assertDontSee('Sanne Visser')
                ->assertSee(__('There is no User with that email address.'));
        }
    }

    public function test_an_admin_can_make_a_user_an_instance_admin_and_take_it_away_again(): void
    {
        $sanne = User::factory()->create(['email' => 'sanne@example.com']);

        $page = Livewire::test('pages::admin.users')->set('email', 'sanne@example.com')->call('find');

        $page->call('makeAdmin');
        $this->assertTrue($sanne->fresh()->is_instance_admin);

        $page->call('removeAdmin');
        $this->assertFalse($sanne->fresh()->is_instance_admin);
    }

    public function test_the_last_instance_admin_cannot_be_demoted(): void
    {
        Livewire::test('pages::admin.users')
            ->set('email', $this->admin->email)
            ->call('find')
            ->call('removeAdmin')
            ->assertHasErrors('admin');

        $this->assertTrue($this->admin->fresh()->is_instance_admin);
    }

    public function test_a_user_can_be_created_with_a_password_that_is_shown_once(): void
    {
        $page = Livewire::test('pages::admin.users')
            ->set('newName', 'Daan Oosterhuis')
            ->set('newEmail', 'daan@example.com')
            ->call('createUser')
            ->assertHasNoErrors();

        $daan = User::whereEmail('daan@example.com')->sole();
        $password = $page->get('createdPassword');

        $this->assertSame('Daan Oosterhuis', $daan->name);
        $this->assertNotNull($daan->email_verified_at);
        $this->assertFalse($daan->is_instance_admin);
        $this->assertNull($daan->current_household_id);
        $this->assertSame(0, Household::count());
        $this->assertGreaterThanOrEqual(16, strlen($password));
        $this->assertTrue(Hash::check($password, $daan->password));

        $page->assertSee($password)->set('newName', 'x');
        $this->assertNull($page->get('createdPassword'), 'The password is gone once the admin moves on.');
    }

    public function test_a_user_cannot_be_created_with_an_email_address_that_is_taken(): void
    {
        User::factory()->create(['email' => 'daan@example.com']);

        Livewire::test('pages::admin.users')
            ->set('newName', 'Daan')
            ->set('newEmail', 'DAAN@example.com')
            ->call('createUser')
            ->assertHasErrors('newEmail');

        $this->assertDatabaseCount('users', 2);
    }
}
