<?php

namespace Tests\Feature\Instance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A page of the Instance Admin section stays open in a browser. Every action on it is a request of its own, and
 * must meet the same doors as the first one: an admin who is demoted, or who loses their second factor, stops
 * being able to act at once, not at the next page load.
 *
 * Livewire::test() skips middleware, so these tests send the update request a browser sends.
 */
class AdminLivewireUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Open the Users page as the admin, as a browser does, and take the state Livewire keeps in the page.
     */
    private function openUsersPage(User $admin): string
    {
        $html = $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get(route('instance.users'))->assertOk()->getContent();

        // The page holds several components (the sidebar has its own); take the page itself.
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded);

            if ((json_decode($snapshot, true)['memo']['name'] ?? null) === 'pages::admin.users') {
                return $snapshot;
            }
        }

        $this->fail('The Users page did not carry its own snapshot.');
    }

    /**
     * Do what the page does when the admin presses "Create User".
     */
    private function createUserFromOpenPage(string $snapshot): TestResponse
    {
        return $this->postJson(Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot,
            'updates' => ['newName' => 'Late Created', 'newEmail' => 'late@example.com'],
            'calls' => [['path' => '', 'method' => 'createUser', 'params' => []]],
        ]]], ['X-Livewire' => 'true']);
    }

    public function test_an_open_page_still_works_for_an_admin_who_is_still_an_admin(): void
    {
        $admin = User::factory()->instanceAdmin()->create();

        $this->createUserFromOpenPage($this->openUsersPage($admin))->assertOk();

        $this->assertTrue(User::whereEmail('late@example.com')->exists(), 'The update request reaches the page.');
    }

    public function test_an_admin_who_is_demoted_cannot_act_on_a_page_that_was_left_open(): void
    {
        $admin = User::factory()->instanceAdmin()->create();
        User::factory()->instanceAdmin()->create();
        $snapshot = $this->openUsersPage($admin);

        $admin->forceFill(['is_instance_admin' => false])->save();

        $this->createUserFromOpenPage($snapshot)->assertNotFound();

        $this->assertFalse(User::whereEmail('late@example.com')->exists());
    }

    public function test_an_admin_who_loses_their_second_factor_cannot_act_on_a_page_that_was_left_open(): void
    {
        $admin = User::factory()->instanceAdmin()->create();
        $snapshot = $this->openUsersPage($admin);

        $admin->forceFill(['two_factor_confirmed_at' => null])->save();

        $this->createUserFromOpenPage($snapshot);

        $this->assertFalse(User::whereEmail('late@example.com')->exists());
    }

    public function test_the_user_found_cannot_be_chosen_by_id_from_the_browser(): void
    {
        $this->actingAs(User::factory()->instanceAdmin()->create());
        $someone = User::factory()->create();

        foreach (['userId' => $someone->id, 'searched' => true] as $property => $value) {
            try {
                Livewire::test('pages::admin.users')->set($property, $value);

                $this->fail("{$property} can be set from the browser, which would find a User without their email address.");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
