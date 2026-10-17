import { I18n } from 'laravel-vue-i18n';
import { describe, expect, it } from 'vite-plus/test';
import plurals from '../../tests/fixtures/plurals.json';

// SPIKE (#16): the client pluralises exactly like Laravel, from the same files
// and the same fixture as tests/Feature/Platform/LocaleTest.php.
const languages = import.meta.glob<{ default: Record<string, string> }>(
    '../../lang/*.json',
    { eager: true },
);

describe('plurals', () => {
    for (const [locale, cases] of Object.entries(plurals)) {
        it(`match the server in ${locale}`, async () => {
            const i18n = new I18n({
                lang: locale,
                resolve: (lang: string) =>
                    languages[`../../lang/${lang}.json`].default,
            });
            await i18n.loadLanguageAsync(locale);

            for (const { key, count, expected } of cases) {
                expect(i18n.transChoice(key, count)).toBe(expected);
            }
        });
    }
});
