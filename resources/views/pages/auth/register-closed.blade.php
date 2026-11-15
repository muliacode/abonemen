<x-layouts::auth :title="__('Registration is closed')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Registration is closed')" :description="__('This Abonemen does not take new accounts right now. If you were invited, ask the person who runs it to create your account.')" />

        <div class="space-x-1 rtl:space-x-reverse text-sm text-muted">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
