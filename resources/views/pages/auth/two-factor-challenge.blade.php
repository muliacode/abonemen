<x-layouts::auth :title="__('Two-factor authentication')">
    <div class="flex flex-col gap-6">
        <div
            class="relative w-full h-auto"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                focusOtp() {
                    this.$nextTick(() => this.$refs.otp?.querySelector('input')?.focus());
                },
                init() {
                    if (! this.showRecoveryInput) {
                        this.focusOtp();
                    }
                },
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;

                    this.code = '';
                    this.recovery_code = '';

                    $nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : this.focusOtp();
                    });
                },
            }"
        >
            <div class="flex w-full flex-col text-center">
                <x-page-heading>
                    <span x-show="!showRecoveryInput">{{ __('Authentication code') }}</span>
                    <span x-show="showRecoveryInput">{{ __('Recovery code') }}</span>
                </x-page-heading>
                <flux:subheading>
                    <span x-show="!showRecoveryInput">{{ __('Enter the authentication code provided by your authenticator application.') }}</span>
                    <span x-show="showRecoveryInput">{{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}</span>
                </flux:subheading>
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}">
                @csrf

                <div class="space-y-5 text-center">
                    <div x-show="!showRecoveryInput">
                        <div class="flex items-center justify-center my-5" x-ref="otp">
                            <flux:otp
                                x-model="code"
                                length="6"
                                name="code"
                                :label="__('OTP Code')"
                                class="mx-auto"
                             />
                        </div>
                    </div>

                    <div x-show="showRecoveryInput">
                        <div class="my-5">
                            <flux:input
                                type="text"
                                name="recovery_code"
                                :label="__('Recovery code')"
                                x-ref="recovery_code"
                                x-bind:required="showRecoveryInput"
                                autocomplete="one-time-code"
                                x-model="recovery_code"
                            />
                        </div>

                        @error('recovery_code')
                            <flux:text color="red">
                                {{ $message }}
                            </flux:text>
                        @enderror
                    </div>

                    <flux:button
                        variant="primary"
                        type="submit"
                        class="w-full"
                    >
                        {{ __('Continue') }}
                    </flux:button>
                </div>

                <div class="mt-5 space-x-0.5 text-sm leading-5 text-center">
                    <span class="text-zinc-600 dark:text-zinc-400">{{ __('or you can') }}</span>
                    <button type="button" class="inline font-medium underline cursor-pointer" x-show="!showRecoveryInput" @click="toggleInput()">{{ __('login using a recovery code') }}</button>
                    <button type="button" class="inline font-medium underline cursor-pointer" x-show="showRecoveryInput" @click="toggleInput()">{{ __('login using an authentication code') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::auth>
