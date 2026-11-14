<x-layouts::auth :title="config('app.name')" :brand="false">
    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-4">
            <x-app-logo-icon class="mb-2 size-16 text-accent" />

            <x-page-heading>{{ config('app.name') }}</x-page-heading>

            <p class="max-w-[24ch] text-[1.375rem] leading-snug font-medium">
                {{ __('Know exactly what you pay for, when, and why, without giving up your privacy.') }}
            </p>
        </div>

        <div class="flex flex-col gap-3">
            @auth
                <flux:button variant="primary" class="w-full" :href="auth()->user()->homeUrl()" data-test="welcome-dashboard-link">
                    {{ __('Dashboard') }}
                </flux:button>
            @else
                <flux:button variant="primary" class="w-full" :href="route('login')" data-test="welcome-login-link">
                    {{ __('Log in') }}
                </flux:button>

                @if (Route::has('register'))
                    <flux:button class="w-full" :href="route('register')" data-test="welcome-register-link">
                        {{ __('Register') }}
                    </flux:button>
                @endif
            @endauth
        </div>
    </div>
</x-layouts::auth>
