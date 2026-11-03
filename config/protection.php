<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master Key
    |--------------------------------------------------------------------------
    |
    | The key that protects every other key. Each Household has its own data
    | key, stored wrapped by this master key, and the keys for sign-in data
    | are derived from it. It must stay outside the database: whoever holds
    | both the database and this key can read everything.
    |
    | It is 32 random bytes written as "base64:...". Losing it means losing
    | the data, so keep a copy somewhere safe. Generate one with
    | "php artisan master-key:generate".
    |
    */

    'master_key' => env('ABONEMEN_MASTER_KEY'),

];
