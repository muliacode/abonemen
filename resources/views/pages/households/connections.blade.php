<?php

use App\Enums\Connection;
use App\Enums\ConnectionState;
use App\Exceptions\ConnectionException;
use App\Models\Household;
use App\Support\Connections;
use App\Support\MailConfiguration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Connections')] class extends Component
{
    public Household $household;

    /**
     * The Owner's confirmation that they have read what is sent and where it goes. It is asked for every time.
     */
    public bool $confirmed = false;

    /**
     * Where email stands for this Household. Locked, so that the page only says what is true.
     */
    #[Locked]
    public ConnectionState $state = ConnectionState::Off;

    /**
     * Whether the Owner has switched email on, whatever the instance says now. Switching off is always possible.
     */
    #[Locked]
    public bool $switchedOn = false;

    #[Locked]
    public string $destination = '';

    public ?string $status = null;

    public function mount(Household $household): void
    {
        Gate::authorize('update', $household);

        $this->household = $household;
        $this->refresh();
    }

    public function enable(Connections $connections): void
    {
        Gate::authorize('update', $this->household);

        if (! $this->state->canBeSwitchedOn()) {
            return;
        }

        $this->validate(
            ['confirmed' => ['accepted']],
            ['confirmed.accepted' => __('Tick the box to confirm that you have read this.')],
        );

        try {
            $connections->confirm(Connection::Email, $this->household, Auth::user());
        } catch (ConnectionException) {
            $this->refresh();

            return;
        }

        $this->refresh();
        $this->status = __('Saved.');
    }

    public function disable(Connections $connections): void
    {
        Gate::authorize('update', $this->household);

        $connections->disable(Connection::Email, $this->household);

        $this->refresh();
        $this->status = __('Saved.');
    }

    public function updated(): void
    {
        $this->status = null;
    }

    private function refresh(): void
    {
        $connections = app(Connections::class);

        $this->state = $connections->stateFor(Connection::Email, $this->household);
        $this->switchedOn = $connections->hasSwitchedOn(Connection::Email, $this->household);
        $this->destination = app(MailConfiguration::class)->destination();
        $this->confirmed = false;
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Connections')" :subheading="__('What this Household sends out, and whether it is on')">
        <div class="space-y-6" data-test="connection-email">
            <h3 class="text-base font-semibold">{{ __('Email') }}</h3>

            @php
                $message = match ($state) {
                    \App\Enums\ConnectionState::Unavailable => __('Your Instance Admin has not made email available.'),
                    \App\Enums\ConnectionState::Paused => __('Email is not delivered right now, so nothing is sent.'),
                    \App\Enums\ConnectionState::On => __('Email is on for this Household.'),
                    \App\Enums\ConnectionState::ConfirmAgain => __('Email is paused until you confirm again. The mail server or what is sent has changed since you confirmed.'),
                    \App\Enums\ConnectionState::Off => null,
                };
            @endphp

            @if ($message)
                <p class="font-medium" data-test="email-state-message">{{ $message }}</p>
            @endif

            {{-- One form, so that the status message waits inside it for the save (docs/accessibility.md). --}}
            <form wire:submit="enable" class="space-y-4">
                <x-error-summary />

                @if ($state->canBeSwitchedOn())
                    <x-disclosure.email :destination="$destination" />

                    <flux:checkbox id="confirmed" wire:model="confirmed" :label="__('I have read what email sends and where it goes')" data-test="confirm-checkbox" />
                @endif

                <div class="flex flex-wrap items-center gap-4">
                    @if ($state->canBeSwitchedOn())
                        <flux:button variant="primary" type="submit" data-test="enable-email-button">{{ __('Switch email on') }}</flux:button>
                    @endif

                    @if ($switchedOn)
                        <flux:button type="button" wire:click="disable" data-test="disable-email-button">{{ __('Switch email off') }}</flux:button>
                    @endif

                    <x-status-message :message="$status" />
                </div>
            </form>
        </div>
    </x-pages::settings.layout>
</section>
