<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-paper text-ink antialiased">
        <x-skip-link />

        {{-- The rail is the ink of the shell; the current page is a tab of paper joined to the page (see app.css). --}}
        <flux:sidebar sticky collapsible="mobile" class="[--mark-knock:var(--color-rail)]">
            <flux:sidebar.header>
                <x-app-logo href="{{ auth()->user()->homeUrl() }}" wire:navigate class="text-rail-text" />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <livewire:household-switcher />

            @php
                // The Household item is "current" on the pages of the Household the User is working in. The pages of another
                // Household, reached from Settings > Households, belong to Settings, so that the rail never claims a
                // Household that is not the one in the switcher.
                $user = auth()->user();
                $viewed = request()->route('household');
                $viewed = is_string($viewed) ? \App\Models\Household::where('ulid', $viewed)->first() : $viewed;
                $inHousehold = request()->routeIs('households.edit', 'households.members', 'households.connections')
                    && $viewed !== null && $user->isCurrentHousehold($viewed);
            @endphp

            <flux:sidebar.nav :aria-label="__('Main')">
                <flux:sidebar.item icon="home" :href="$user->homeUrl()" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                @if ($user->currentHousehold)
                    <flux:sidebar.item icon="users" :href="route('households.edit', $user->currentHousehold)" :current="$inHousehold" wire:navigate data-test="household-link">
                        {{ __('Household') }}
                    </flux:sidebar.item>
                @endif

                <flux:sidebar.item icon="adjustments-horizontal" :href="route('profile.edit')" :current="! $inHousehold && request()->routeIs('profile.edit', 'security.edit', 'appearance.edit', 'language.edit', 'households.*')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            @if (auth()->user()->is_instance_admin)
                <flux:sidebar.nav :aria-label="__('Instance Admin')">
                    <flux:sidebar.item icon="building-office-2" :href="route('instance.households')" :current="request()->routeIs('instance.*')" wire:navigate data-test="instance-admin-link">
                        {{ __('Instance Admin') }}
                    </flux:sidebar.item>
                </flux:sidebar.nav>
            @endif

            <flux:spacer />

            <x-user-menu />
        </flux:sidebar>

        <flux:header class="lg:hidden bg-rail text-rail-text [--mark-knock:var(--color-rail)]">
            <x-app-logo href="{{ auth()->user()->homeUrl() }}" wire:navigate class="-ms-2 text-rail-text" />

            <flux:spacer />

            <flux:sidebar.toggle icon="bars-2" inset="right" />
        </flux:header>

        {{ $slot }}

        <livewire:create-household-modal />

        @fluxScripts
    </body>
</html>
