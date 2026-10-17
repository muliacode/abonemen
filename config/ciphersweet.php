<?php

use App\Support\Crypto\GlobalBackendFactory;
use App\Support\Crypto\GlobalKeyProviderFactory;

/*
 * spatie/laravel-ciphersweet encrypts account-level fields (user email and
 * name) under the global data key. Household fields use the Encrypted cast
 * instead, on CipherSweet's multi-tenant mode. See "Fields" in
 * docs/product/technical-design.md. The key always comes from the key
 * hierarchy, never from the environment.
 */
return [

    'backend' => 'custom',

    'backends' => [
        'custom' => GlobalBackendFactory::class,
    ],

    'provider' => 'custom',

    'providers' => [
        'custom' => GlobalKeyProviderFactory::class,
    ],

    // Optional fields may be empty; required ones are validated before saving.
    'permit_empty' => true,

];
