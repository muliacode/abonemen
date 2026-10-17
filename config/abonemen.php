<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master key file
    |--------------------------------------------------------------------------
    |
    | Path of the file holding the master key, absolute or relative to the
    | project root. Keep it outside public/ and out of backups. This is the
    | preferred form; ABONEMEN_MASTER_KEY is the alternative for containers.
    |
    | The key itself is never read into the configuration, so it cannot end up
    | in the cached config file. Set ABONEMEN_MASTER_KEY as a real environment
    | variable: with a cached config, Laravel no longer reads the .env file.
    |
    */

    'master_key_file' => env('ABONEMEN_MASTER_KEY_FILE'),

];
