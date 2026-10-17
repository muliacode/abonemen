<script setup lang="ts">
// SPIKE (#13): the second factor after a password, with a TOTP code or a passkey.
import { Head, router, useForm } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';

defineProps<{ hasTotp: boolean; hasPasskey: boolean }>();

const form = useForm({ code: '' });

const {
    verify,
    isLoading,
    isSupported,
    error: passkeyError,
} = usePasskeyVerify({
    routes: {
        options: '/two-factor-challenge/passkey/options',
        submit: '/two-factor-challenge/passkey',
    },
    onSuccess: (response) => router.visit(response.redirect ?? '/dashboard'),
});

const submit = () => form.post('/two-factor-challenge');
</script>

<template>
    <Head title="Confirm it is you" />

    <main>
        <h1>Confirm it is you</h1>

        <p v-if="hasPasskey">
            <button
                type="button"
                :disabled="!isSupported || isLoading"
                @click="verify"
            >
                Use a passkey
            </button>
        </p>
        <p v-if="passkeyError" role="alert">{{ passkeyError }}</p>

        <form v-if="hasTotp" @submit.prevent="submit">
            <p>
                <label for="code">Code from your authenticator app</label>
                <input
                    id="code"
                    v-model="form.code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    :aria-invalid="form.errors.code ? 'true' : undefined"
                    :aria-describedby="
                        form.errors.code ? 'code-error' : undefined
                    "
                />
                <span v-if="form.errors.code" id="code-error">{{
                    form.errors.code
                }}</span>
            </p>
            <button type="submit" :disabled="form.processing">Continue</button>
        </form>
    </main>
</template>
