<?php

use App\Data\UserHousehold;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Households')] class extends Component {
    /**
     * @return Collection<int, UserHousehold>
     */
    #[Computed]
    public function households(): Collection
    {
        return Auth::user()->toUserHouseholds();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Households') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Households')" :subheading="__('The households you belong to')">
        <div class="flex items-center justify-end">
            <flux:modal.trigger name="create-household">
                <flux:button variant="primary" icon="plus" data-test="households-new-household-button">
                    {{ __('New household') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        <div class="mt-6 space-y-3">
            @forelse ($this->households as $household)
                <div class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="household-row">
                    <div>
                        <div class="font-medium">{{ $household->name }}</div>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $household->roleLabel }}</flux:text>
                    </div>

                    <flux:tooltip :content="$household->canManage ? __('Edit household') : __('View household')">
                        <flux:button
                            variant="ghost"
                            size="sm"
                            :icon="$household->canManage ? 'pencil' : 'eye'"
                            :href="route('households.edit', $household->ulid)"
                            :aria-label="$household->canManage ? __('Edit household') : __('View household')"
                            wire:navigate
                            :data-test="$household->canManage ? 'household-edit-button' : 'household-view-button'"
                        />
                    </flux:tooltip>
                </div>
            @empty
                <flux:text class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                    {{ __('You don\'t belong to any households yet. Create one to get started.') }}
                </flux:text>
            @endforelse
        </div>
    </x-pages::settings.layout>
</section>
