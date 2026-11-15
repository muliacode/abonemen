<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Setup token
    |--------------------------------------------------------------------------
    |
    | A new instance has no Instance Admin. Whoever installed it proves that by
    | entering this token on /setup, which then makes them the first admin and
    | closes. Without a token, /setup does not exist.
    |
    */

    'setup_token' => env('ABONEMEN_SETUP_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Instance Admin path
    |--------------------------------------------------------------------------
    |
    | Where the Instance Admin section lives. It is a 404 for everyone who is
    | not an Instance Admin, and the path is only a first line of quiet: the
    | real protection is the sign-in and the second factor. The default is
    | readable and not the one everybody tries; choose your own.
    |
    */

    'admin_path' => trim((string) env('ABONEMEN_ADMIN_PATH', 'instance'), '/'),

];
