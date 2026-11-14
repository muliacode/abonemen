<?php

namespace Tests\Feature\Accessibility;

use App\Models\User;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\RendersPages;
use Tests\TestCase;

/**
 * Anything a User needs to know stays on the page, in a live region, instead of disappearing on a timer.
 */
class StatusMessageTest extends TestCase
{
    use RefreshDatabase;
    use RendersPages;

    public function test_saving_the_profile_confirms_in_a_status_message(): void
    {
        $this->actingAs(User::factory()->withHousehold()->create());

        $component = Livewire::test('pages::settings.profile')
            ->set('name', 'Test User')
            ->call('updateProfileInformation');

        $this->assertStatus('Profile updated.', $this->parse($component->html()));
        $component->assertNotDispatched('toast-show');
    }

    public function test_saving_the_household_confirms_in_a_status_message(): void
    {
        $user = User::factory()->withHousehold()->create();
        $this->actingAs($user);

        $component = Livewire::test('pages::households.edit', ['household' => $user->currentHousehold])
            ->set('name', 'Renamed')
            ->call('updateHousehold');

        $this->assertStatus('Household updated.', $this->parse($component->html()));
        $component->assertNotDispatched('toast-show');
    }

    public function test_changing_the_password_confirms_in_a_status_message(): void
    {
        $this->actingAs(User::factory()->withHousehold()->create())->withSession(['auth.password_confirmed_at' => time()]);

        $component = Livewire::test('pages::settings.security')
            ->set('current_password', 'password')
            ->set('password', 'a-new-Password-1')
            ->set('password_confirmation', 'a-new-Password-1')
            ->call('updatePassword');

        $this->assertStatus('Password updated.', $this->parse($component->html()));
        $component->assertNotDispatched('toast-show');
    }

    public function test_saving_the_language_confirms_on_the_page_that_follows(): void
    {
        $this->actingAs(User::factory()->withHousehold()->create());

        Livewire::test('pages::settings.language')->set('locale', 'nl')->call('updateLanguage');

        $this->assertStatus('Taal bijgewerkt.', $this->parse($this->get(route('language.edit'))->getContent()));
    }

    public function test_creating_a_household_confirms_on_the_page_that_follows(): void
    {
        $user = User::factory()->withHousehold()->create();
        $this->actingAs($user);

        Livewire::test('create-household-modal')->set('name', 'Parents')->call('createHousehold');

        $this->assertStatus('Household created.', $this->parse($this->get(route('dashboard'))->getContent()));
    }

    public function test_a_status_from_a_sign_in_screen_is_announced(): void
    {
        $page = $this->parse($this->withSession(['status' => 'We have emailed your password reset link.'])->get(route('password.request'))->getContent());

        $this->assertStatus('We have emailed your password reset link.', $page);
    }

    /**
     * The message is inside a live region (role="status"), so assistive technology reads it out.
     */
    private function assertStatus(string $message, DOMXPath $page): void
    {
        $statuses = array_map(
            fn ($node) => trim(preg_replace('/\s+/', ' ', $node->textContent)),
            iterator_to_array($page->query('//*[@role="status"]')),
        );

        $this->assertContains($message, $statuses);
    }
}
