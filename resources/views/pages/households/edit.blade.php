<?php

use App\Models\Household;
use App\Rules\HouseholdSettingRules;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\Intl\Currencies;

new class extends Component
{
    public Household $household;

    public string $name = '';

    public string $mainCurrency = '';

    public string $timezone = '';

    public function mount(Household $household): void
    {
        Gate::authorize('view', $household);

        $this->household = $household;
        $this->name = $household->name;
        $this->mainCurrency = $household->main_currency;
        $this->timezone = $household->timezone;
    }

    public function updateHousehold(): void
    {
        Gate::authorize('update', $this->household);

        $validated = $this->validate([
            'name' => HouseholdSettingRules::name(),
            'mainCurrency' => HouseholdSettingRules::mainCurrency(),
            'timezone' => HouseholdSettingRules::timezone(),
        ]);

        $this->household->update([
            'name' => $validated['name'],
            'main_currency' => $validated['mainCurrency'],
            'timezone' => $validated['timezone'],
        ]);

        Flux::toast(variant: 'success', text: __('Household updated.'));
    }

    #[Computed]
    public function canUpdate(): bool
    {
        return Gate::allows('update', $this->household);
    }

    public function render()
    {
        $title = $this->canUpdate
            ? __('Edit :name', ['name' => $this->household->name])
            : __('View :name', ['name' => $this->household->name]);

        return $this->view()->title($title);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Households') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Households')" :subheading="__('Manage your household settings')">
        <div class="space-y-6">
            @if ($this->canUpdate)
                <form wire:submit="updateHousehold" class="space-y-6">
                    <flux:input wire:model="name" :label="__('Household name')" required data-test="household-name-input" />

                    <flux:select wire:model="mainCurrency" :label="__('Main currency')" required data-test="household-currency-input">
                        @foreach (Currencies::getNames(app()->getLocale()) as $code => $currencyName)
                            <flux:select.option :value="$code">{{ $code }} – {{ $currencyName }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="timezone" :label="__('Time zone')" required data-test="household-timezone-input">
                        @foreach (timezone_identifiers_list() as $identifier)
                            <flux:select.option :value="$identifier">{{ $identifier }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:button variant="primary" type="submit" data-test="household-save-button">
                        {{ __('Save') }}
                    </flux:button>
                </form>
            @else
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Household name') }}</dt>
                        <dd class="font-medium">{{ $household->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Main currency') }}</dt>
                        <dd class="font-medium">{{ $household->main_currency }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Time zone') }}</dt>
                        <dd class="font-medium">{{ $household->timezone }}</dd>
                    </div>
                </dl>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
