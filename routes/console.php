<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SPIKE (#14): a command the container test queues, to show the worker runs it.
Artisan::command('spike:touch {path}', function (string $path) {
    file_put_contents($path, 'yes');
})->purpose('SPIKE (#14): write a file');
