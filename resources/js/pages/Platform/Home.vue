<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

// SPIKE (#16): translated text, plurals and a language switch.
const page = usePage();
const locales = page.props.locales as string[];
const count = ref(0);

const names: Record<string, string> = {
    nl: 'Nederlands',
    en: 'English',
    id: 'Bahasa Indonesia',
    fr: 'Français',
    de: 'Deutsch',
};

const switchTo = (event: Event) =>
    router.post(
        '/locale',
        { locale: (event.target as HTMLSelectElement).value },
        { preserveScroll: true, preserveState: true },
    );
</script>

<template>
    <Head :title="$t('Subscriptions')" />

    <main>
        <h1>Abonemen</h1>
        <p>{{ $t('A self-hostable, privacy-first subscription tracker.') }}</p>

        <p>
            <label for="locale">{{ $t('Language') }}</label>
            <select id="locale" :value="page.props.locale" @change="switchTo">
                <option
                    v-for="locale in locales"
                    :key="locale"
                    :value="locale"
                    :lang="locale"
                >
                    {{ names[locale] }}
                </option>
            </select>
        </p>

        <p>
            <label for="count">{{ $t('Subscriptions') }}</label>
            <input id="count" v-model.number="count" type="number" min="0" />
        </p>
        <p data-testid="empty-state" aria-live="polite">
            {{
                $tChoice(
                    '{0} No subscriptions yet|{1} :count subscription|[2,*] :count subscriptions',
                    count,
                )
            }}
        </p>
        <p data-testid="renewals">
            {{
                $tChoice(
                    ':count subscription renews today|:count subscriptions renew today',
                    count,
                )
            }}
        </p>
    </main>
</template>
