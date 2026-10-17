# Spike 5: laravel-vue-i18n with Inertia v3 and five locales

Issue: [#16](https://github.com/muliacode/abonemen/issues/16). Requirement: NFR-09.

**Outcome: the design holds as written.** One set of `lang/{locale}.json` files serves Laravel and Vue, plurals agree on both sides in all five launch locales, and switching language needs no full reload. The proof of concept is on the [`spike/16-vue-i18n`](https://github.com/muliacode/abonemen/tree/spike/16-vue-i18n) branch and is not merged; #82 builds the real pipeline.

## What was built

| Part | How |
| --- | --- |
| Files | `lang/nl.json`, `en.json`, `id.json`, `fr.json` and `de.json`, keyed by the English sentence, as Laravel's JSON translations are |
| Choosing the locale | A `SetLocale` middleware takes the session's choice, then the browser's `Accept-Language` (`Request::getPreferredLanguage()` over the supported locales). `HandleInertiaRequests` shares `locale` and `locales` as page props; `<html lang>` comes from the app locale |
| Vue | `laravel-vue-i18n` 2.9 installed through Inertia v3's `withApp` hook, with the server's locale. Each language is its own lazily loaded chunk. A `router.on('success')` listener follows the `locale` prop after every visit: it loads the language and sets `<html lang>` |
| Switching | `POST /locale` stores the choice in the session and redirects back; the page sends it as an Inertia visit with `preserveState`, so there is no full page load |
| Tests | A shared fixture of plural cases (`tests/fixtures/plurals.json`) checked by Pest with `trans_choice()` and by Vitest with `transChoice()`; a Pest check that every file has the same keys; Playwright for switching and for the browser's language on a first visit |

## Results

### Done when

- [x] **Plurals work in nl, en, id, fr and de**, and the server and the client give the same result for 0, 1, 2 and 21 of two kinds of string:
  - **implicit forms** (`one|many`), which follow each language's own rule:
    - French treats 0 as singular: "0 abonnement est renouvelé aujourd’hui".
    - Indonesian has one form: "3 langganan diperpanjang hari ini".
  - **explicit ranges** (`{0} …|{1} …|[2,*] …`) for the empty state.
- [x] **Outcome written.**

Also shown:

- **Switching needs no full page load.** A marker set on `window` survives the switch, the text and `<html lang>` change, and the choice is still there after a reload.
- **A first visit follows the browser's language.**

### Cost

Each language file is a separate chunk of under 0.4 kB gzipped, loaded only when needed. The library adds about 4.3 kB gzipped to the main bundle (58.8 → 63.1 kB).

## Findings

- **`laravel-vue-i18n` ports Laravel's `MessageSelector`**, including its per-locale plural rules, so plural strings can be written once and trusted on both sides. Keep the shared fixture: it catches a drift if either side changes.
- **Inertia v3 has a `withApp` hook** for installing Vue plugins with the first page's props. A `setup()` callback is no longer needed.
- **The Vite plugin for PHP language files is not needed.** Laravel's own messages (validation, passwords, throttling) are translated on the server and reach Vue as finished strings. Translating those still needs Laravel's PHP language files for nl, id, fr and de, for example from `laravel-lang/lang` (MIT), but only on the server.
- **The `Accept-Language` fallback is the first supported locale**, not `app.locale`: Symfony's `getPreferredLanguage()` returns the first entry when nothing matches. With the list in the design's order that is Dutch. #82 should decide whether a Japanese browser gets Dutch or English and order the list accordingly (or pass the fallback explicitly).
- **Translators must keep the range syntax.** Strings such as `{0} …|[2,*] …` keep their brackets in every language, and a language with one form (Indonesian) uses `{0} …|[1,*] …`. #82's contributor notes should say so, and the key-parity test should stay.
- **A label rule fix is needed on `main`**, the same one as in #175: the language `<select>` has an ordinary `<label for>`.

## Not covered

Number, currency and date formatting with `Intl` (#83), the translation workflow and a translation platform (#82), and right-to-left languages, none of which are planned.
