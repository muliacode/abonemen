<?php

namespace Tests\Feature\Accessibility;

use App\Enums\Connection;
use App\Models\User;
use App\Support\Connections;
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
        $this->openRegistration();

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

    public function test_a_failed_setup_lists_each_invalid_field_in_a_summary(): void
    {
        config(['abonemen.setup_token' => 'the-setup-token']);

        $response = $this->from(route('setup'))->followingRedirects()
            ->post(route('setup.store'), ['token' => 'the-setup-token', 'name' => '', 'email' => 'not-an-email', 'password' => 'x', 'password_confirmation' => 'y']);

        $this->assertSummaryLinksToInvalidFields($this->parse($response->getContent()), ['name', 'email', 'password']);
    }

    public function test_a_wrong_setup_token_is_named_in_the_summary(): void
    {
        config(['abonemen.setup_token' => 'the-setup-token']);

        $response = $this->from(route('setup'))->followingRedirects()
            ->post(route('setup.store'), ['token' => 'not-it', 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'a-good-password-1', 'password_confirmation' => 'a-good-password-1']);

        $this->assertSummaryLinksToInvalidFields($this->parse($response->getContent()), ['token']);
    }

    public function test_a_failed_general_form_lists_each_invalid_field_in_a_summary(): void
    {
        $this->actingAs(User::factory()->instanceAdmin()->create());

        $html = Livewire::test('pages::admin.general')
            ->set('language', 'xx')
            ->set('currency', 'ZZZ')
            ->set('timezone', 'Mars/Olympus')
            ->call('save')
            ->html();

        $this->assertSummaryLinksToInvalidFields($this->parse($html), ['language', 'currency', 'timezone']);
    }

    public function test_dropping_the_second_factor_without_the_password_is_named_in_the_summary(): void
    {
        $this->actingAs(User::factory()->instanceAdmin()->create());

        $html = Livewire::test('pages::admin.general')
            ->set('requireSecondFactor', false)
            ->call('save')
            ->html();

        $this->assertSummaryLinksToInvalidFields($this->parse($html), ['password']);
    }

    public function test_a_failed_email_form_lists_each_invalid_field_in_a_summary(): void
    {
        $this->actingAs(User::factory()->instanceAdmin()->create());

        $html = Livewire::test('pages::admin.email')
            ->set('fromAddress', 'not-an-email')
            ->set('host', '')
            ->call('save')
            ->html();

        $this->assertSummaryLinksToInvalidFields($this->parse($html), ['fromAddress', 'host']);
    }

    public function test_a_failed_new_user_lists_each_invalid_field_in_a_summary(): void
    {
        $this->actingAs(User::factory()->instanceAdmin()->create());

        $html = Livewire::test('pages::admin.users')
            ->set('newName', '')
            ->set('newEmail', 'not-an-email')
            ->call('createUser')
            ->html();

        $this->assertSummaryLinksToInvalidFields($this->parse($html), ['newName', 'newEmail']);
    }

    public function test_the_last_instance_admin_refused_deleting_their_account_is_told_in_the_summary(): void
    {
        $this->actingAs(User::factory()->instanceAdmin()->create());

        $html = Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'password')
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

    public function test_switching_email_on_without_the_confirmation_names_the_checkbox_in_the_summary(): void
    {
        $owner = User::factory()->withHousehold()->create();
        $this->actingAs($owner);

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.home.example']);
        app(Connections::class)->makeAvailable(Connection::Email);

        $html = Livewire::test('pages::households.connections', ['household' => $owner->currentHousehold])
            ->call('enable')
            ->html();

        $this->assertSummaryLinksToInvalidFields($this->parse($html), ['confirmed']);
    }

    public function test_adding_a_member_without_a_name_names_the_name_field_in_the_summary(): void
    {
        $owner = User::factory()->withHousehold()->create();
        $this->actingAs($owner);

        $html = Livewire::test('add-member-form', ['household' => $owner->currentHousehold])
            ->set('name', '')
            ->call('add')
            ->html();

        $this->assertSummaryLinksToInvalidFields($this->parse($html), ['name'], 'add-member-');
    }

    public function test_adding_a_member_with_a_taken_label_names_the_nickname_field_in_the_summary(): void
    {
        $owner = User::factory()->withHousehold()->create();
        $this->actingAs($owner);

        $html = Livewire::test('add-member-form', ['household' => $owner->currentHousehold])
            ->set('name', $owner->name)
            ->call('add')
            ->html();

        $this->assertSummaryLinksToInvalidFields($this->parse($html), ['nickname'], 'add-member-');
    }

    public function test_editing_a_member_with_a_blank_name_names_the_name_field_in_the_summary_of_that_dialog_only(): void
    {
        $owner = User::factory()->withHousehold()->create();
        $this->actingAs($owner);
        $member = $owner->currentHousehold->members()->firstOrFail();

        $html = Livewire::test('pages::households.members', ['household' => $owner->currentHousehold])
            ->call('edit', $member->id)
            ->set('editName', '')
            ->call('saveEdit')
            ->html();

        $page = $this->parse($html);

        $this->assertSummaryLinksToInvalidFields($page, ['editName'], 'edit-member-');
        $this->assertCount(0, $page->query('//*[@data-error-summary][@aria-labelledby="remove-member-error-summary-heading"]'), 'The other dialog is not told about it.');
    }

    public function test_a_refused_removal_is_announced_in_the_remove_dialog_and_links_to_its_button(): void
    {
        $owner = User::factory()->withHousehold()->create();
        $this->actingAs($owner);
        $member = $owner->currentHousehold->members()->firstOrFail();

        $html = Livewire::test('pages::households.members', ['household' => $owner->currentHousehold])
            ->call('confirmRemove', $member->id)
            ->call('remove')
            ->html();

        $page = $this->parse($html);
        $summary = $page->query('//*[@data-error-summary][@aria-labelledby="remove-member-error-summary-heading"]');

        $this->assertCount(1, $summary);
        $this->assertCount(1, $page->query('//*[@id="remove-member-remove"]'), 'The link lands on the Remove button.');
        $this->assertSame('#remove-member-remove', $page->query('.//a', $summary->item(0))->item(0)->getAttribute('href'));
    }
}
