<?php

use App\Concerns\PasswordValidationRules;
use App\Enums\Locale;
use App\Rules\HouseholdSettingRules;
use App\Support\PlatformSettings;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('General')] class extends Component {
    use PasswordValidationRules;

    public bool $registrationOpen = false;

    public bool $requireSecondFactor = true;

    public string $language = '';

    public string $currency = '';

    public string $timezone = '';

    /**
     * Asked only to turn the second factor requirement off: lowering a protection needs the admin's password.
     */
    public string $password = '';

    /**
     * Whether the requirement was on when the page opened, so the password is asked only for turning it off.
     * Locked: if the browser could set it, the password for lowering a protection could be skipped.
     */
    #[Locked]
    public bool $wasRequired = true;

    /**
     * The confirmation shown next to Save until the admin changes something again.
     */
    public ?string $status = null;

    public function mount(PlatformSettings $settings): void
    {
        $this->registrationOpen = $settings->registrationIsOpen();
        $this->requireSecondFactor = $this->wasRequired = $settings->requireSecondFactor();
        $this->language = $settings->defaultLanguage()->value;
        $this->currency = $settings->defaultCurrency();
        $this->timezone = $settings->defaultTimezone();
    }

    public function save(PlatformSettings $settings): void
    {
        $loweringProtection = $this->wasRequired && ! $this->requireSecondFactor;

        $this->validate([
            'registrationOpen' => ['boolean'],
            'requireSecondFactor' => ['boolean'],
            'language' => ['required', Rule::in(Locale::codes())],
            'currency' => HouseholdSettingRules::mainCurrency(),
            'timezone' => HouseholdSettingRules::timezone(),
            'password' => $loweringProtection ? $this->currentPasswordRules() : ['nullable'],
        ]);

        $settings->openRegistration($this->registrationOpen);
        $settings->setRequireSecondFactor($this->requireSecondFactor);
        $settings->setDefaults(Locale::from($this->language), $this->currency, $this->timezone);

        $this->wasRequired = $this->requireSecondFactor;
        $this->reset('password');
        $this->status = __('Saved.');
    }

    public function updated(string $property): void
    {
        $this->status = null;
    }
}; ?>

<section class="w-full">
    <x-page-header :title="__('General')" :description="__('Choices that shape the whole instance')" />

    <x-admin-nav />

    <form wire:submit="save" class="flex max-w-xl flex-col gap-10">
        <x-error-summary />

        <fieldset class="flex flex-col gap-5">
            <legend class="mb-1 text-lg font-semibold">{{ __('Access') }}</legend>

            <flux:switch
                id="registrationOpen"
                wire:model="registrationOpen"
                :label="__('Anyone can register')"
                :description="__('When this is off, the sign-up page explains that registration is closed and you create Users yourself.')"
                data-test="registration-switch"
            />

            {{-- Live, on purpose: turning this off must show the password field at once. See docs/accessibility.md. --}}
            <flux:switch
                id="requireSecondFactor"
                wire:model.live="requireSecondFactor"
                :label="__('Require a second factor for this section')"
                :description="__('Recommended. Instance Admins open this section only with two-factor authentication or a passkey. Turning it off is your own security decision.')"
                data-test="second-factor-switch"
            />

            @if ($wasRequired && ! $requireSecondFactor)
                <flux:input
                    id="password"
                    wire:model="password"
                    :label="__('Your password')"
                    :description="__('Confirm your password to lower this protection.')"
                    type="password"
                    required
                    autocomplete="current-password"
                    viewable
                />
            @endif
        </fieldset>

        <fieldset class="flex flex-col gap-5">
            <legend class="mb-1 text-lg font-semibold">{{ __('New Households') }}</legend>
            <p class="-mt-2 text-muted">{{ __('What a new Household starts with. Households that exist are not changed.') }}</p>

            <flux:select id="language" wire:model="language" :label="__('Language')" :description="__('Used when neither the person nor their browser picks one.')" required>
                @foreach (Locale::cases() as $locale)
                    <flux:select.option :value="$locale->value">{{ $locale->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select id="currency" wire:model="currency" :label="__('Main currency')" required>
                @foreach (\Symfony\Component\Intl\Currencies::getNames(app()->getLocale()) as $code => $currencyName)
                    <flux:select.option :value="$code">{{ $code }} – {{ $currencyName }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select id="timezone" wire:model="timezone" :label="__('Time zone')" required>
                @foreach (timezone_identifiers_list() as $identifier)
                    <flux:select.option :value="$identifier">{{ $identifier }}</flux:select.option>
                @endforeach
            </flux:select>
        </fieldset>

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit" data-test="save-general-button">{{ __('Save') }}</flux:button>

            <x-status-message :message="$status" />
        </div>
    </form>
</section>
