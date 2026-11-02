<?php

use App\Http\Middleware\EnsureHouseholdMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::prefix('{current_household}')
    ->middleware(['auth', 'verified', EnsureHouseholdMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

require __DIR__.'/settings.php';
