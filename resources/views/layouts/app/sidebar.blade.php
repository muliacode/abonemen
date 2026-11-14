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

            <flux:sidebar.nav :aria-label="__('Main')">
                <flux:sidebar.item icon="home" :href="auth()->user()->homeUrl()" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="adjustments-horizontal" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit', 'appearance.edit', 'language.edit', 'households.*')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-user-menu />
        </flux:sidebar>

        <flux:header class="lg:hidden bg-rail text-rail-text [--mark-knock:var(--color-rail)]">
            <x-app-logo href="{{ auth()->user()->homeUrl() }}" wire:navigate class="text-rail-text" />

            <flux:spacer />

            <flux:sidebar.toggle icon="bars-2" inset="right" />
        </flux:header>

        {{ $slot }}

        <livewire:create-household-modal />

        @fluxScripts
    </body>
</html>
