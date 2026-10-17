<?php

use Tests\Arch\Support\Domains;

// Rules from "Code organisation" in docs/product/technical-design.md (EXT-01).

arch('no debug calls are left in the app')
    ->expect('App')
    ->not->toUse([
        'dd',
        'ddd',
        'dump',
        'ray',
        'var_dump',
        'print_r',
        'debug_zval_dump',
        'xdebug_break',
        'die',
        'exit',
    ]);

foreach (Domains::ALL as $domain) {
    $foreign = Domains::foreignModels($domain);

    if ($foreign === []) {
        continue;
    }

    // One test per namespace: Pest's `not->toUse()` silently passes when the
    // target is an array of namespaces, so each one must be a plain string.
    foreach (Domains::namespaces($domain) as $namespace) {
        arch("{$namespace} does not use the models of other domains")
            ->expect($namespace)
            ->not->toUse($foreign);
    }
}

arch('only Support\Crypto touches cryptographic libraries')
    ->expect([
        'ParagonIE\CipherSweet',
        'Illuminate\Support\Facades\Crypt',
        'Illuminate\Contracts\Encryption',
        'Illuminate\Encryption',
        ...array_filter(get_defined_functions()['internal'], fn (string $f): bool => str_starts_with($f, 'sodium_')),
    ])
    ->toOnlyBeUsedIn('App\Support\Crypto');

arch('Support\Crypto is used only by the Crypto domain, models and wiring')
    ->expect('App\Support\Crypto')
    ->toOnlyBeUsedIn([
        'App\Support\Crypto',
        ...Domains::namespaces('Crypto'),
        'App\Models',
        'App\Providers',
        'App\Console',
        // EnsureMasterKey, which keeps the app closed until a master key is set.
        'App\Http\Middleware',
    ]);

it('gives every model an owning domain', function () {
    $models = array_map(class_basename(...), Domains::modelClasses());

    expect($models)->each->toBeIn(array_keys(Domains::MODEL_OWNERS))
        ->and(array_values(Domains::MODEL_OWNERS))->each->toBeIn(Domains::ALL);
});
