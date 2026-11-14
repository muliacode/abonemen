<x-layouts::auth :title="config('app.name')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="config('app.name')"
            :description="__('Know exactly what you pay for, when, and why, without giving up your privacy.')"
        />

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
