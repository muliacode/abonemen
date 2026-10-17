<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the home page through Inertia', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Platform/Home'));
});

it('sets the document language from the application locale', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="en">', false);

    $this->get(route('home'), ['Accept-Language' => 'nl-NL,nl;q=0.9'])
        ->assertSee('<html lang="nl">', false);
});

it('has a single h1 in the page component', function () {
    $source = file_get_contents(resource_path('js/pages/Platform/Home.vue'));

    expect(substr_count($source, '<h1'))->toBe(1);
});
