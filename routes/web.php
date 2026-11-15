<?php

use App\Http\Controllers\SetupController;
use App\Http\Middleware\EnsureHouseholdMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('setup', [SetupController::class, 'show'])->name('setup');
Route::post('setup', [SetupController::class, 'store'])->name('setup.store');

Route::prefix('{current_household}')
    ->middleware(['auth', 'verified', EnsureHouseholdMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

require __DIR__.'/settings.php';
require __DIR__.'/instance.php';
