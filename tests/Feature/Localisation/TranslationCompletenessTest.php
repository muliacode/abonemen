<?php

namespace Tests\Feature\Localisation;

use App\Enums\Locale;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Every text the app can show must exist, translated, in every launch language.
 *
 * Texts are written in English where they are used, as __('Some text'), and the
 * languages other than English translate them in lang/{code}.json. English is the
 * fallback: a text missing from a language would show in English, which this test refuses.
 */
class TranslationCompletenessTest extends TestCase
{
    /**
     * Where texts are used: the app itself, and the parts of packages whose texts people see.
     *
     * Every path must exist, so that a package moving a folder fails here instead of quietly shrinking the scan.
     *
     * @var list<string>
     */
    private const SOURCES = [
        'app',
        'resources/views',
        'vendor/laravel/fortify/src',
        'vendor/laravel/passkeys/src',
        'vendor/laravel/framework/src/Illuminate/Auth/Notifications',
        'vendor/laravel/framework/src/Illuminate/Notifications/resources/views',
        'vendor/laravel/framework/src/Illuminate/Mail/resources/views',
        'vendor/livewire/flux/stubs/resources/views/flux/modal',
        'vendor/livewire/flux/stubs/resources/views/flux/input',
        'vendor/livewire/flux/stubs/resources/views/flux/otp',
    ];

    /**
     * Texts that are the same in a language as in English, because the word is shared or is a name.
     * Anything else that equals its English text has simply not been translated.
     *
     * @var array<string, list<string>>
     */
    private const SAME_AS_ENGLISH = [
        'nl' => ['Dashboard', 'Platform', 'Repository'],
        'id' => ['Editor', 'Passkeys', 'Platform'],
        'fr' => ['Documentation'],
        'de' => ['Dashboard', 'Name', 'Passkeys', 'Repository', 'System'],
    ];

    /** @var list<string>|null */
    private ?array $texts = null;

    /** @var array<string, array<string, mixed>> */
    private array $loaded = [];

    public function test_every_text_exists_in_every_launch_language(): void
    {
        $keys = $this->textsInUse();

        $this->assertGreaterThan(100, count($keys), 'The scan should find the texts of the existing screens.');

        $report = [];

        foreach ($this->otherLanguages() as $code) {
            $translations = $this->translations($code);
            $missing = array_values(array_filter($keys, fn (string $key) => ! array_key_exists($key, $translations)));

            if ($missing !== []) {
                $report[] = count($missing)." text(s) are missing from lang/{$code}.json:\n- ".implode("\n- ", $missing);
            }
        }

        $this->assertSame([], $report, "\n".implode("\n\n", $report));
    }

    public function test_no_text_is_left_in_english_in_another_language(): void
    {
        $report = [];

        foreach ($this->otherLanguages() as $code) {
            $translations = $this->translations($code);
            $untranslated = array_values(array_filter(
                $this->textsInUse(),
                fn (string $key) => ($translations[$key] ?? null) === $key && ! in_array($key, self::SAME_AS_ENGLISH[$code] ?? [], true),
            ));

            if ($untranslated !== []) {
                $report[] = count($untranslated)." text(s) in lang/{$code}.json are still the English text:\n- ".implode("\n- ", $untranslated);
            }
        }

        $this->assertSame([], $report, "\n".implode("\n\n", $report));
    }

    public function test_no_translation_is_empty(): void
    {
        foreach ($this->otherLanguages() as $code) {
            $empty = array_keys(array_filter($this->translations($code), fn ($value) => is_string($value) && trim($value) === ''));

            $this->assertSame([], $empty, "These texts are empty in lang/{$code}.json:\n- ".implode("\n- ", $empty));
        }
    }

    public function test_every_translation_keeps_the_placeholders_of_its_text(): void
    {
        foreach ($this->otherLanguages() as $code) {
            $translations = $this->translations($code);
            $broken = [];

            foreach ($this->textsInUse() as $key) {
                $translation = $translations[$key] ?? null;

                if (is_string($translation) && $this->placeholders($key) !== $this->placeholders($translation)) {
                    $broken[] = $key;
                }
            }

            $this->assertSame([], $broken, "These translations in lang/{$code}.json lost or changed a :placeholder:\n- ".implode("\n- ", $broken));
        }
    }

    /**
     * The launch languages that have a translation file: every one except English, whose texts are the keys.
     *
     * @return list<string>
     */
    private function otherLanguages(): array
    {
        return array_values(array_filter(Locale::codes(), fn (string $code) => $code !== 'en'));
    }

    /**
     * @return array<string, mixed>
     */
    private function translations(string $code): array
    {
        if (isset($this->loaded[$code])) {
            return $this->loaded[$code];
        }

        $path = lang_path("{$code}.json");

        $this->assertFileExists($path);

        $decoded = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded);

        return $this->loaded[$code] = $decoded;
    }

    /**
     * Every English text written as __('...'), trans('...'), Lang::get('...') or @lang('...') in the sources,
     * plus the page titles, which are translated when they are shown.
     *
     * Texts that name a PHP translation file entry, such as 'validation.unique', are not listed: those
     * come from lang/{code}/*.php.
     *
     * @return list<string>
     */
    private function textsInUse(): array
    {
        if ($this->texts !== null) {
            return $this->texts;
        }

        $pattern = '/(?:__|trans|Lang::get|@lang)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s';
        $titles = '/#\[Title\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*\)\]/s';
        $texts = [];

        foreach (self::SOURCES as $source) {
            $this->assertDirectoryExists(base_path($source), "{$source} is scanned for texts, so it must exist.");

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($source), RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                foreach ([$pattern, $titles] as $regex) {
                    preg_match_all($regex, $contents, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

                    foreach ($matches as $match) {
                        $text = $this->joinedStrings($contents, $match);

                        if ($this->isDynamic($text) || $this->namesAPhpTranslation($text)) {
                            continue;
                        }

                        $texts[$text] = true;
                    }
                }
            }
        }

        $found = array_keys($texts);
        sort($found);

        return $this->texts = $found;
    }

    /**
     * Read a text that may be written as several strings joined by dots, as the framework's mail view does.
     *
     * @param  array<int, array{0: string, 1: int}>  $match
     */
    private function joinedStrings(string $contents, array $match): string
    {
        $text = stripcslashes($match[2][0]);
        $offset = $match[0][1] + strlen($match[0][0]);

        while (preg_match('/\G\s*\.\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s', $contents, $next, PREG_OFFSET_CAPTURE, $offset)) {
            $text .= stripcslashes($next[2][0]);
            $offset = $next[0][1] + strlen($next[0][0]);
        }

        return $text;
    }

    private function isDynamic(string $text): bool
    {
        return $text === '' || str_contains($text, '$') || str_contains($text, '{{');
    }

    private function namesAPhpTranslation(string $text): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_-]*(\.[a-z0-9_-]+)+$/', $text);
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[A-Za-z_]+/', $text, $matches);

        // :Attribute and :ATTRIBUTE are forms of :attribute that Laravel fills in capitalised.
        $placeholders = array_unique(array_map('strtolower', $matches[0]));
        sort($placeholders);

        return $placeholders;
    }
}
