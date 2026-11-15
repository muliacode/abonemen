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

            <flux:button type="submit" variant="primary" class="w-full" data-test="setup-button">
                {{ __('Create the Instance Admin') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
