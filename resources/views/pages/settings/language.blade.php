<?php

use App\Enums\Locale;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Language settings')] class extends Component {
    public string $locale = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->locale = Auth::user()->locale?->value ?? app()->getLocale();
    }

    /**
     * Save the language the signed-in user wants to see the app in.
     */
    public function updateLanguage(): void
    {
        $validated = $this->validate([
            'locale' => ['required', Rule::enum(Locale::class)],
        ]);

        Auth::user()->update(['locale' => $validated['locale']]);

        $this->redirectRoute('language.edit', navigate: true);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Language settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Language')" :subheading="__('Choose the language Abonemen is shown in, and the language of the emails it sends you')">
        <form wire:submit="updateLanguage" class="space-y-6">
            <flux:select wire:model="locale" :label="__('Language')" data-test="language-select">
                @foreach (Locale::cases() as $option)
                    <flux:select.option :value="$option->value" lang="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button variant="primary" type="submit" data-test="language-save-button">
                {{ __('Save') }}
            </flux:button>
        </form>
    </x-pages::settings.layout>
</section>
