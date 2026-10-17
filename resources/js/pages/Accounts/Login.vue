<script setup lang="ts">
// SPIKE (#13): password sign-in and passwordless sign-in with a passkey.
import { Head, router, useForm } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';

const form = useForm({ email: '', password: '', remember: false });

const {
    verify,
    isLoading,
    isSupported,
    error: passkeyError,
} = usePasskeyVerify({
    remember: () => form.remember,
    onSuccess: (response) => router.visit(response.redirect ?? '/dashboard'),
});

const submit = () =>
    form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Sign in" />

    <main>
        <h1>Sign in</h1>

        <form @submit.prevent="submit">
            <p>
                <label for="email">Email address</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email webauthn"
                    required
                    :aria-invalid="form.errors.email ? 'true' : undefined"
                    :aria-describedby="
                        form.errors.email ? 'email-error' : undefined
                    "
                />
                <span v-if="form.errors.email" id="email-error">{{
                    form.errors.email
                }}</span>
            </p>
            <p>
                <label for="password">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                />
            </p>
            <p>
                <input id="remember" v-model="form.remember" type="checkbox" />
                <label for="remember">Keep me signed in</label>
            </p>
            <button type="submit" :disabled="form.processing">Sign in</button>
        </form>

        <p>
            <button
                type="button"
                :disabled="!isSupported || isLoading"
                @click="verify"
            >
                Sign in with a passkey
            </button>
        </p>
        <p v-if="passkeyError" role="alert">{{ passkeyError }}</p>
    </main>
</template>
