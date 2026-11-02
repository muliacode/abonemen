<?php

use App\Actions\Households\CreateHousehold;
use App\Rules\HouseholdSettingRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public string $name = '';

    public string $mainCurrency = 'EUR';

    public string $timezone = '';

    public function mount(): void
    {
        $this->timezone = config('app.timezone');
    }

    public function createHousehold(CreateHousehold $createHousehold): void
    {
        $validated = $this->validate([
            'name' => HouseholdSettingRules::name(),
            'mainCurrency' => HouseholdSettingRules::mainCurrency(),
            'timezone' => HouseholdSettingRules::timezone(),
        ]);

        $household = $createHousehold->handle(
            Auth::user(),
            $validated['name'],
            $validated['mainCurrency'],
            $validated['timezone'],
        );

        $this->dispatch('close-modal', name: 'create-household');

        $this->reset('name');

        Flux::toast(variant: 'success', text: __('Household created.'));

        $this->redirectRoute('dashboard', ['current_household' => $household->ulid], navigate: true);
    }
}; ?>

<flux:modal name="create-household" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form wire:submit="createHousehold" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Create a new household') }}</flux:heading>
            <flux:subheading>{{ __('A household is where you and the people you share costs with keep your subscriptions.') }}</flux:subheading>
        </div>

        <flux:input wire:model="name" :label="__('Household name')" type="text" required autofocus data-test="create-household-name" />

        <flux:select wire:model="mainCurrency" :label="__('Main currency')" required data-test="create-household-currency">
            @foreach (\Symfony\Component\Intl\Currencies::getNames() as $code => $currencyName)
                <flux:select.option :value="$code">{{ $code }} – {{ $currencyName }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model="timezone" :label="__('Time zone')" required data-test="create-household-timezone">
            @foreach (timezone_identifiers_list() as $identifier)
                <flux:select.option :value="$identifier">{{ $identifier }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button variant="primary" type="submit" data-test="create-household-submit">
                {{ __('Create household') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
