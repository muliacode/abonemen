<?php

use App\Http\Controllers\Crypto\FileController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Platform/Home')->name('home');

Route::get('/files/{file}', [FileController::class, 'show'])
    ->whereUlid('file')
    ->name('files.show');
