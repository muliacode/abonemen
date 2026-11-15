<?php

use App\Support\PlatformSettings;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Registration')] class extends Component {
    public bool $open = false;

    /**
     * The confirmation shown next to the switch until the admin does something else.
     */
    public ?string $status = null;

    public function mount(PlatformSettings $settings): void
    {
        $this->open = $settings->registrationIsOpen();
    }

    public function updatedOpen(PlatformSettings $settings): void
    {
        $settings->openRegistration($this->open);

        $this->status = $this->open ? __('Registration is open.') : __('Registration is closed.');
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('Registration')" :description="__('Choose whether anyone can create an account on this instance')" />

    <x-admin-nav />

    <form wire:submit.prevent class="mt-6 max-w-xl">
        <flux:switch
            wire:model.live="open"
            :label="__('Anyone can register')"
            :description="__('When this is off, the sign-up page explains that registration is closed and you create Users yourself.')"
            data-test="registration-switch"
        />

        <x-status-message class="mt-4" :message="$status" />
    </form>
</section>
