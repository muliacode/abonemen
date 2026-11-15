<?php

use App\Mail\TestMail;
use App\Support\MailConfiguration;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Email')] class extends Component {
    public string $fromName = '';

    public string $fromAddress = '';

    public string $host = '';

    public string $port = '587';

    public string $scheme = 'smtp';

    public string $username = '';

    /**
     * Write-only: a saved password is never sent back to the page, and an empty one leaves it as it is.
     */
    public string $password = '';

    #[Locked]
    public bool $hasPassword = false;

    /**
     * Where the settings in use come from: saved, env or undelivered. Locked, so that it only says what is true.
     */
    #[Locked]
    public string $source = 'undelivered';

    public ?string $status = null;

    public ?string $testResult = null;

    public bool $testFailed = false;

    public function mount(PlatformSettings $settings, MailConfiguration $mail): void
    {
        $saved = $settings->mail();

        $this->fromName = $saved['from_name'] ?? (string) config('mail.from.name');
        $this->fromAddress = $saved['from_address'] ?? '';
        $this->hasPassword = filled($saved['password'] ?? null);
        $this->source = $mail->source();

        // Show what is in use when .env set it up; a log or test mailer has nothing worth showing.
        $smtp = $saved ?? ($this->source === 'env' ? $this->environmentSmtp() : []);

        $this->host = (string) ($smtp['host'] ?? '');
        $this->port = (string) ($smtp['port'] ?? '587');
        $this->scheme = (string) ($smtp['scheme'] ?? 'smtp');
        $this->username = (string) ($smtp['username'] ?? '');
    }

    public function save(PlatformSettings $settings, MailConfiguration $mail): void
    {
        $this->validate([
            'fromName' => ['required', 'string', 'max:255'],
            'fromAddress' => ['required', 'email', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'scheme' => ['required', Rule::in(['smtp', 'smtps'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $settings->saveMail([
            'from_name' => $this->fromName,
            'from_address' => $this->fromAddress,
            'host' => $this->host,
            'port' => (int) $this->port,
            'scheme' => $this->scheme,
            'username' => $this->username === '' ? null : $this->username,
            'password' => $this->password !== '' ? $this->password : ($settings->mail()['password'] ?? null),
        ]);

        $mail->apply();

        $this->hasPassword = filled($settings->mail()['password']);
        $this->source = $mail->source();
        $this->reset('password', 'testResult', 'testFailed');
        $this->status = __('Saved.');
    }

    /**
     * Send a test email to the admin, with whatever settings are in use now.
     */
    public function sendTest(PlatformSettings $settings): void
    {
        $this->status = null;
        $this->testFailed = true;

        if ($this->source === 'undelivered') {
            $this->testResult = __('Set up email first. Until then, emails are only written to a log.');

            return;
        }

        try {
            Mail::to(Auth::user()->email)->send(new TestMail);
        } catch (Throwable $error) {
            $this->testResult = $this->withoutSecrets($error->getMessage(), $settings);

            return;
        }

        $settings->markMailTested();

        $this->testFailed = false;
        $this->testResult = __('The test email was sent to :email.', ['email' => Auth::user()->email]);
    }

    /**
     * Forget what was saved here, so that .env applies again.
     */
    public function resetToEnvironment(PlatformSettings $settings, MailConfiguration $mail): void
    {
        $settings->clearMail();
        $mail->apply();

        $this->mount($settings, $mail);
        $this->reset('password', 'testResult', 'testFailed');
        $this->status = __('Saved.');
    }

    public function updated(): void
    {
        $this->status = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentSmtp(): array
    {
        return (array) config('mail.mailers.smtp');
    }

    /**
     * An error from the mail server can repeat what was sent to it. The password must not end up on the page.
     */
    private function withoutSecrets(string $message, PlatformSettings $settings): string
    {
        $secret = $settings->mail()['password'] ?? config('mail.mailers.smtp.password');

        return filled($secret) ? str_replace((string) $secret, '••••', $message) : $message;
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('Email')" :description="__('How Abonemen sends password resets and verification emails')" />

    <x-admin-nav />

    <div class="mb-8 max-w-xl" data-test="mail-source">
        @if ($source === 'saved')
            <p>{{ __('Using the settings saved here') }}</p>
        @elseif ($source === 'env')
            <p>{{ __('Using the settings from .env') }}</p>
        @else
            <p class="font-medium text-danger">{{ __('Emails are not delivered yet') }}</p>
            <p class="text-muted">{{ __('The mailer only writes emails to a log, so nobody receives a password reset or a verification link. Fill in your SMTP server below.') }}</p>
        @endif
    </div>

    <form wire:submit="save" class="flex max-w-xl flex-col gap-5">
        <x-error-summary />

        <flux:input id="fromName" wire:model="fromName" :label="__('Sender name')" type="text" required autocomplete="off" />
        <flux:input id="fromAddress" wire:model="fromAddress" :label="__('Sender email address')" type="email" required autocomplete="off" />
        <flux:input id="host" wire:model="host" :label="__('SMTP server')" :description="__('For example smtp.example.com')" type="text" required autocomplete="off" />
        <flux:input id="port" wire:model="port" :label="__('Port')" type="number" min="1" max="65535" required autocomplete="off" />

        <flux:select id="scheme" wire:model="scheme" :label="__('Connection')" required>
            <flux:select.option value="smtp">{{ __('Upgrade to a secure connection (STARTTLS, usually port 587)') }}</flux:select.option>
            <flux:select.option value="smtps">{{ __('Secure from the start (SSL/TLS, usually port 465)') }}</flux:select.option>
        </flux:select>

        <flux:input id="username" wire:model="username" :label="__('Username')" type="text" autocomplete="off" />
        <flux:input
            id="password"
            wire:model="password"
            :label="__('Password')"
            :description="$hasPassword ? __('A password is saved. Leave this empty to keep it.') : null"
            type="password"
            autocomplete="new-password"
            viewable
        />

        <div class="flex flex-wrap items-center gap-4">
            <flux:button variant="primary" type="submit" data-test="save-email-button">{{ __('Save') }}</flux:button>

            <flux:button type="button" wire:click="sendTest" data-test="send-test-email-button">{{ __('Send a test email') }}</flux:button>

            @if ($source === 'saved')
                <flux:button type="button" variant="ghost" wire:click="resetToEnvironment" wire:confirm="{{ __('Forget the settings saved here and use .env again?') }}" data-test="reset-email-button">
                    {{ __('Go back to .env') }}
                </flux:button>
            @endif
        </div>

        <x-status-message :message="$status" />

        @if ($testResult)
            <div role="{{ $testFailed ? 'alert' : 'status' }}" @class(['text-sm font-medium', 'text-danger' => $testFailed, 'text-success' => ! $testFailed]) data-test="test-email-result">
                {{ $testResult }}
            </div>
        @endif
    </form>
</section>
