<?php

use App\Enums\Connection;
use App\Exceptions\ConnectionException;
use App\Support\Connections;
use App\Support\MailConfiguration;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Connections')] class extends Component {
    /**
     * Locked, so that what the page says is what is true: only the actions below change it.
     */
    #[Locked]
    public bool $emailAvailable = false;

    #[Locked]
    public int $householdsOn = 0;

    #[Locked]
    public int $householdsWaiting = 0;

    /**
     * The confirmation shown beside the buttons until the admin does something else.
     */
    public ?string $status = null;

    public function mount(): void
    {
        $this->refresh();
    }

    public function makeEmailAvailable(Connections $connections): void
    {
        try {
            $connections->makeAvailable(Connection::Email);
        } catch (ConnectionException) {
            // Nobody would receive it. The page already says so, and nothing changed to confirm.
            $this->refresh();

            return;
        }

        $this->refresh();
        $this->status = __('Saved.');
    }

    public function makeEmailUnavailable(Connections $connections): void
    {
        $connections->makeUnavailable(Connection::Email);

        $this->refresh();
        $this->status = __('Saved.');
    }

    public function updated(): void
    {
        $this->status = null;
    }

    /**
     * Whether anyone receives email: the same test as for account emails, and what must hold before email can be
     * made available to Households.
     */
    #[Computed]
    public function mailDelivers(): bool
    {
        return app(MailConfiguration::class)->delivers();
    }

    private function refresh(): void
    {
        $connections = app(Connections::class);
        $counts = $connections->householdCounts(Connection::Email);

        $this->emailAvailable = $connections->isAvailable(Connection::Email);
        $this->householdsOn = $counts['on'];
        $this->householdsWaiting = $counts['waiting'];
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('Connections')" :description="__('Everything this instance sends out, and whether it is on')" />

    <x-admin-nav />

    <div class="flex max-w-2xl flex-col gap-8">
        <section aria-labelledby="email-connection-heading" data-test="connection-email">
            <h2 id="email-connection-heading" class="text-base font-semibold">{{ __('Email to Households') }}</h2>
            <p class="text-muted">{{ __('Invitations and Reminders. They contain Household data, so an Owner must also switch email on for their Household.') }}</p>

            <p class="mt-3 font-medium" data-test="email-state">
                {{ $emailAvailable ? __('Available to Households') : __('Off') }}
            </p>

            @unless ($this->mailDelivers)
                <p class="mt-1" data-test="email-blocked">
                    {{ __('Set up email first. Until then nobody would receive these emails.') }}
                    <flux:link :href="route('instance.email')" wire:navigate>{{ __('Set up email') }}</flux:link>
                </p>
            @endunless

            {{-- A form, so that the status message waits inside it for the change (docs/accessibility.md). --}}
            <form wire:submit="makeEmailAvailable" class="mt-3 flex flex-wrap items-center gap-4">
                @if ($emailAvailable)
                    <flux:button type="button" wire:click="makeEmailUnavailable" data-test="email-unavailable-button">{{ __('Make email unavailable') }}</flux:button>
                @else
                    <flux:button type="submit" variant="primary" :disabled="! $this->mailDelivers" data-test="email-available-button">{{ __('Make email available to Households') }}</flux:button>
                @endif

                <x-status-message :message="$status" />
            </form>

            <dl class="mt-4 grid grid-cols-[1fr_auto] gap-x-6 gap-y-1">
                <dt>{{ __('Households that switched it on') }}</dt>
                <dd class="font-medium" data-test="households-on">{{ $householdsOn }}</dd>
                <dt>{{ __('Households that must confirm again') }}</dt>
                <dd class="font-medium" data-test="households-waiting">{{ $householdsWaiting }}</dd>
            </dl>
        </section>

        <section aria-labelledby="account-emails-heading" data-test="connection-account-emails">
            <h2 id="account-emails-heading" class="text-base font-semibold">{{ __('Account emails') }}</h2>
            <p class="text-muted">{{ __('Password resets and verification links. They carry no Household data, so they depend only on email being set up.') }}</p>

            <p class="mt-3 font-medium" data-test="account-emails-state">
                {{ $this->mailDelivers ? __('On because email is set up') : __('Off until email is set up') }}
            </p>
        </section>
    </div>
</section>
