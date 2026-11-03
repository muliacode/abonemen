<?php

namespace Tests\Feature\Localisation;

use App\Enums\Locale;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Every text the app can show must exist in every launch language.
 *
 * Texts are written in English where they are used, as __('Some text'), and the
 * languages other than English translate them in lang/{code}.json. English is the
 * fallback: a text missing from a language shows in English, which this test refuses.
 */
class TranslationCompletenessTest extends TestCase
{
    /**
     * Where texts are used: the app itself, and the packages whose messages people see.
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
    ];

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
            $broken = [];

            foreach ($this->textsInUse() as $key) {
                $translation = $this->translations($code)[$key] ?? null;

                if (is_string($translation) && $this->placeholders($key) !== $this->placeholders($translation)) {
                    $broken[] = $key;
                }
            }

            $this->assertSame([], $broken, "These translations in lang/{$code}.json lost or changed a :placeholder:\n- ".implode("\n- ", $broken));
        }
    }

    public function test_the_launch_languages_are_the_ones_the_concept_promises(): void
    {
        $this->assertEqualsCanonicalizing(['en', 'nl', 'id', 'fr', 'de'], Locale::codes());
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
        $path = lang_path("{$code}.json");

        $this->assertFileExists($path);

        $decoded = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded);

        return $decoded;
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
        static $found = null;

        if ($found !== null) {
            return $found;
        }

        $pattern = '/(?:__|trans|Lang::get|@lang)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s';
        $titles = '/#\[Title\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*\)\]/s';
        $texts = [];

        foreach (self::SOURCES as $source) {
            $root = base_path($source);

            if (! is_dir($root)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
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

        return $found;
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
