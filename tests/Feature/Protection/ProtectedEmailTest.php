<?php

namespace Tests\Feature\Protection;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ProtectedEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_email_is_unreadable_in_the_raw_database_but_still_signs_the_user_in(): void
    {
        $user = User::factory()->create(['email' => 'sanne@example.com']);

        $this->assertStringNotContainsString('sanne', (string) DB::table('users')->value('email'));
        $this->assertStringNotContainsString('example.com', (string) DB::table('users')->value('email'));
        $this->assertSame('sanne@example.com', $user->fresh()->email);

        $this->post(route('login.store'), ['email' => 'sanne@example.com', 'password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_sign_in_ignores_case_and_surrounding_spaces_in_the_email(): void
    {
        $user = User::factory()->create(['email' => 'sanne@example.com']);

        $this->post(route('login.store'), ['email' => '  Sanne@Example.COM ', 'password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_registered_email_is_stored_protected_and_found_again_by_exact_match(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Sanne',
            'email' => 'sanne@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('sanne', (string) DB::table('users')->value('email'));
        $this->assertSame('sanne@example.com', User::whereEmail('sanne@example.com')->sole()->email);
    }

    public function test_finding_a_user_by_email_is_exact_match_only(): void
    {
        User::factory()->create(['email' => 'sanne@example.com']);

        $this->assertTrue(User::whereEmail('SANNE@example.com')->exists());
        $this->assertFalse(User::whereEmail('sanne')->exists());
        $this->assertFalse(User::whereEmail('sanne@example')->exists());
        $this->assertFalse(User::whereEmail('anne@example.com')->exists());
    }

    public function test_registering_with_an_email_that_is_taken_is_refused_whatever_its_case(): void
    {
        User::factory()->create(['email' => 'sanne@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Someone else',
            'email' => 'Sanne@Example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_email_can_only_be_changed_to_one_nobody_else_uses(): void
    {
        $user = User::factory()->create(['email' => 'sanne@example.com']);
        User::factory()->create(['email' => 'daan@example.com']);

        $this->actingAs($user);

        Livewire::test('pages::settings.profile')
            ->set('name', $user->name)
            ->set('email', 'DAAN@example.com')
            ->call('updateProfileInformation')
            ->assertHasErrors('email');

        Livewire::test('pages::settings.profile')
            ->set('name', 'Sanne B')
            ->set('email', 'Sanne@Example.com')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();
    }

    public function test_a_password_reset_works_without_a_readable_email_in_the_token_table(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'sanne@example.com']);

        $this->post(route('password.email'), ['email' => 'Sanne@example.com'])->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('sanne', (string) DB::table('password_reset_tokens')->value('email'));

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            // The link in the email carries the real address, so the reset form can ask for it.
            $this->assertStringContainsString('email=sanne%40example.com', $notification->toMail($user)->actionUrl);

            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => 'sanne@example.com',
                'password' => 'a-new-password',
                'password_confirmation' => 'a-new-password',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->post(route('login.store'), ['email' => 'sanne@example.com', 'password' => 'a-new-password'])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_created_without_model_events_still_get_a_protected_email_and_index(): void
    {
        // The default seeder runs with model events switched off.
        $this->seed(DatabaseSeeder::class);

        $this->assertStringNotContainsString('test@example.com', (string) DB::table('users')->value('email'));
        $this->assertSame('test@example.com', User::whereEmail('test@example.com')->sole()->email);
    }

    public function test_the_blind_index_follows_the_email_when_it_changes(): void
    {
        $user = User::factory()->create(['email' => 'sanne@example.com']);

        $user->update(['email' => 'sanne@new.example.com']);

        $this->assertFalse(User::whereEmail('sanne@example.com')->exists());
        $this->assertTrue(User::whereEmail('sanne@new.example.com')->exists());
    }
}
