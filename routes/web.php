<?php

use App\Http\Controllers\Platform\LocaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Platform/Home')->name('home');

// SPIKE (#16)
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
