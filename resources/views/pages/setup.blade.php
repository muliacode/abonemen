<x-layouts::auth :title="__('Set up Abonemen')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Set up Abonemen')" :description="__('Create the first Instance Admin. You need the setup token from your .env file.')" />

        <form method="POST" action="{{ route('setup.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-error-summary />

            <flux:input
                id="token"
                name="token"
                :label="__('Setup token')"
                :description="__('ABONEMEN_SETUP_TOKEN in your .env file.')"
                type="password"
                required
                autofocus
                autocomplete="off"
                viewable
            />

            <flux:input
                id="name"
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autocomplete="name"
            />

            <flux:input
                id="email"
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
            />

            <flux:input
                id="password"
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:input
                id="password_confirmation"
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            {{-- The browser's time zone becomes the default for new Households. Without script, the server's is used. --}}
            <input type="hidden" name="timezone" x-data x-init="$el.value = Intl.DateTimeFormat().resolvedOptions().timeZone">

            <fieldset class="flex flex-col gap-4">
                <legend class="mb-1 text-base font-semibold">{{ __('Choices for this instance') }}</legend>

                {{-- A hidden 0 first, so that an unticked box says no instead of saying nothing. --}}
                <input type="hidden" name="require_second_factor" value="0">
                <flux:checkbox
                    name="require_second_factor"
                    value="1"
                    :label="__('Require a second factor to open the Instance Admin section')"
                    :description="__('Recommended. Without it, a stolen password is enough to run the instance. Leaving it off is your own security decision.')"
                    :checked="old('require_second_factor', '1') === '1'"
                    data-test="setup-require-second-factor"
                />

                <input type="hidden" name="registration_open" value="0">
                <flux:checkbox
                    name="registration_open"
                    value="1"
                    :label="__('Let anyone register')"
                    :description="__('Off by default. You can change it later under General.')"
                    :checked="old('registration_open', '0') === '1'"
                    data-test="setup-registration-open"
                />
            </fieldset>

            <flux:button type="submit" variant="primary" class="w-full" data-test="setup-button">
                {{ __('Create the Instance Admin') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
