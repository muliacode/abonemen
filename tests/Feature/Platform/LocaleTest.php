<?php

use Inertia\Testing\AssertableInertia as Assert;

/*
 * SPIKE (#16): one set of lang/{locale}.json files, five launch locales.
 */

it('picks the best supported language from the browser', function (string $header, string $locale) {
    $this->get(route('home'), ['Accept-Language' => $header])
        ->assertInertia(fn (Assert $page) => $page->where('locale', $locale));
})->with([
    'Dutch' => ['nl-BE,nl;q=0.9,en;q=0.8', 'nl'],
    'Indonesian' => ['id-ID,id;q=0.9', 'id'],
    'unsupported falls back to the first supported' => ['ja-JP,ja;q=0.9', 'nl'],
]);

it('switches language for the session', function () {
    $this->from(route('home'))->post(route('locale.update'), ['locale' => 'fr'])
        ->assertRedirect(route('home'));

    $this->get(route('home'), ['Accept-Language' => 'de'])
        ->assertSee('<html lang="fr">', false)
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'fr'));
});

it('refuses an unsupported language', function () {
    $this->post(route('locale.update'), ['locale' => 'xx'])->assertSessionHasErrors('locale');
});

it('has the same keys in every language file', function () {
    $keys = fn (string $locale): array => array_keys(json_decode((string) file_get_contents(lang_path("{$locale}.json")), true));

    foreach (config('app.supported_locales') as $locale) {
        expect($keys($locale))->toEqualCanonicalizing($keys('en'));
    }
});

it('pluralises like the shared fixture on the server', function (string $locale) {
    $cases = json_decode((string) file_get_contents(base_path('tests/fixtures/plurals.json')), true)[$locale];

    foreach ($cases as $case) {
        expect(trans_choice($case['key'], $case['count'], [], $locale))->toBe($case['expected']);
    }
})->with(['nl', 'en', 'id', 'fr', 'de']);
