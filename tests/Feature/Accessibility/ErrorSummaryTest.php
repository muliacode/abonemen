<?php

namespace Tests\Feature\Accessibility;

use App\Models\User;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use Tests\Concerns\RendersPages;
use Tests\TestCase;

/**
 * A form that fails to submit says so once, in a summary that links to each invalid field,
 * and every invalid field also shows its own message.
 */
class ErrorSummaryTest extends TestCase
{
    use RefreshDatabase;
    use RendersPages;

    public function test_a_failed_login_lists_each_invalid_field_in_a_summary(): void
    {
        $response = $this->from(route('login'))->followingRedirects()
            ->post(route('login.store'), ['email' => '', 'password' => '']);

        $this->assertSummaryLinksToInvalidFields($this->parse($response->getContent()), ['email', 'password']);
    }

    public function test_a_failed_livewire_form_lists_each_invalid_field_in_a_summary(): void
    {
        $this->actingAs(User::factory()->withHousehold()->create());

        $html = Livewire::test('pages::settings.profile')
            ->set('name', '')
            ->set('email', 'not-an-email')
            ->call('updateProfileInformation')
            ->html();

        $this->assertSummaryLinksToInvalidFields($this->parse($html), ['name', 'email']);
    }

    public function test_a_failed_registration_lists_each_invalid_field_in_a_summary(): void
    {
        $response = $this->from(route('register'))->followingRedirects()
            ->post(route('register.store'), ['name' => '', 'email' => 'not-an-email', 'password' => 'x', 'password_confirmation' => 'y']);

        $this->assertSummaryLinksToInvalidFields($this->parse($response->getContent()), ['name', 'email', 'password']);
    }

    public function test_a_failed_password_reset_request_lists_the_invalid_field_in_a_summary(): void
    {
        $response = $this->from(route('password.request'))->followingRedirects()
            ->post(route('password.email'), ['email' => '']);

        $this->assertSummaryLinksToInvalidFields($this->parse($response->getContent()), ['email']);
    }

    public function test_a_failed_new_password_lists_each_invalid_field_in_a_summary(): void
    {
        $response = $this->from(route('password.reset', ['token' => 'a-token']))->followingRedirects()
            ->post(route('password.update'), ['token' => 'a-token', 'email' => '', 'password' => '', 'password_confirmation' => '']);

        $this->assertSummaryLinksToInvalidFields($this->parse($response->getContent()), ['email', 'password']);
    }

    public function test_a_failed_password_confirmation_lists_the_invalid_field_in_a_summary(): void
    {
        $this->actingAs(User::factory()->withHousehold()->create());

        $response = $this->from(route('password.confirm'))->followingRedirects()
            ->post(route('password.confirm.store'), ['password' => 'wrong']);

        $this->assertSummaryLinksToInvalidFields($this->parse($response->getContent()), ['password']);
    }

    public function test_a_failed_recovery_code_lists_the_invalid_field_in_a_summary(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $response = $this->from(route('two-factor.login'))->followingRedirects()
            ->post(route('two-factor.login.store'), ['recovery_code' => 'not-a-recovery-code']);

        $this->assertSummaryLinksToInvalidFields($this->parse($response->getContent()), ['recovery_code']);
    }

    public function test_a_failed_password_change_lists_each_invalid_field_in_a_summary(): void
    {
        $this->actingAs(User::factory()->withHousehold()->create())->withSession(['auth.password_confirmed_at' => time()]);

        $html = Livewire::test('pages::settings.security')
            ->set('current_password', 'wrong')
            ->set('password', 'x')
            ->call('updatePassword')
            ->html();

        $this->assertSummaryLinksToInvalidFields($this->parse($html), ['current_password', 'password']);
    }

    public function test_a_failed_household_settings_form_lists_each_invalid_field_in_a_summary(): void
    {
        $user = User::factory()->withHousehold()->create();
        $this->actingAs($user);

        $html = Livewire::test('pages::households.edit', ['household' => $user->currentHousehold])
            ->set('name', '')
            ->set('mainCurrency', 'nope')
            ->call('updateHousehold')
            ->html();

        $this->assertSummaryLinksToInvalidFields($this->parse($html), ['name', 'mainCurrency']);
    }

    public function test_a_failed_new_household_lists_each_invalid_field_in_a_summary(): void
    {
        $this->actingAs(User::factory()->withHousehold()->create());

        $html = Livewire::test('create-household-modal')
            ->set('name', '')
            ->call('createHousehold')
            ->html();

        $this->assertSummaryLinksToInvalidFields($this->parse($html), ['name'], 'create-household-');
    }

    public function test_a_failed_account_deletion_lists_the_invalid_field_in_a_summary(): void
    {
        $this->actingAs(User::factory()->withHousehold()->create());

        $html = Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'wrong')
            ->call('deleteUser')
            ->html();

        $this->assertSummaryLinksToInvalidFields($this->parse($html), ['password'], 'delete-user-');
    }

    /**
     * The summary names the problem, has one link per invalid field, and each link lands on that field.
     * The field repeats its own message, which the summary's link repeats word for word.
     *
     * @param  list<string>  $fields
     * @param  string  $prefix  Forms that share a page with another form prefix their field ids.
     */
    private function assertSummaryLinksToInvalidFields(DOMXPath $page, array $fields, string $prefix = ''): void
    {
        $summary = $page->query('//*[@data-error-summary][@aria-labelledby="'.$prefix.'error-summary-heading"]');

        $this->assertCount(1, $summary, 'A failed form has exactly one error summary.');
        $this->assertSame(
            array_map(fn (string $field) => '#'.$prefix.$field, $fields),
            array_map(fn (DOMElement $link) => $link->getAttribute('href'), iterator_to_array($page->query('.//a[starts-with(@href, "#")]', $summary->item(0)))),
            'The links point at the invalid fields, in order.',
        );
        $this->assertNotSame('', trim($page->query('.//h2', $summary->item(0))->item(0)?->textContent ?? ''), 'The summary has a heading.');

        $links = $page->query('.//a[starts-with(@href, "#")]', $summary->item(0));

        $this->assertCount(count($fields), $links, 'One link per invalid field.');

        foreach ($links as $link) {
            $target = $page->query('//*[@id="'.substr($link->getAttribute('href'), 1).'"]')->item(0);

            $this->assertInstanceOf(DOMElement::class, $target, 'The link lands on a field.');
            $this->assertTrue($target->hasAttribute('data-flux-control'), 'The link lands on the control itself.');

            $error = $page->query('./ancestor::ui-field[1]//*[@data-flux-error]', $target)->item(0);

            $this->assertInstanceOf(DOMElement::class, $error, 'The field shows its own message.');
            $this->assertSame(trim($link->textContent), trim($error->textContent));
            $this->assertNotSame('', $error->getAttribute('id'), 'The message can be referred to by the field.');
            $this->assertFalse($error->hasAttribute('role'), 'The summary announces the problem, not each message.');
        }
    }
}
