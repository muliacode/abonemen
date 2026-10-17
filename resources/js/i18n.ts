// SPIKE (#16): one set of lang/{locale}.json files for Laravel and Vue.
// The server decides the locale (session, then Accept-Language) and shares it
// as a page prop; the client loads that language and follows it after every
// visit, so switching language needs no full reload.
import { router } from '@inertiajs/vue3';
import {
    i18nVue,
    loadLanguageAsync,
    getActiveLanguage,
} from 'laravel-vue-i18n';
import type { App } from 'vue';

const languages = import.meta.glob<{ default: Record<string, string> }>(
    '../../lang/*.json',
);

export function installI18n(app: App, locale: string): void {
    app.use(i18nVue, {
        lang: locale,
        fallbackLang: 'en',
        resolve: (lang: string) => languages[`../../lang/${lang}.json`](),
    });

    router.on('success', (event) => {
        const next = event.detail.page.props.locale;
        if (typeof next === 'string' && next !== getActiveLanguage()) {
            document.documentElement.lang = next;
            void loadLanguageAsync(next);
        }
    });
}
