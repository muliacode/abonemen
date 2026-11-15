<?php

use App\Http\Middleware\EnsureInstanceAdmin;
use App\Http\Middleware\RequireSecondFactor;
use App\Support\AdminPath;
use Illuminate\Support\Facades\Route;

/*
| The Instance Admin section. It sits under a configurable path (ABONEMEN_ADMIN_PATH), so that an instance
| need not use the one everybody tries first, and it is a 404 for everyone who is not an Instance Admin.
*/
AdminPath::assertFree(config('abonemen.admin_path'), app('router'));

Route::prefix(config('abonemen.admin_path'))
    ->middleware([EnsureInstanceAdmin::class, 'verified', RequireSecondFactor::class])
    ->group(function () {
        Route::livewire('/', 'pages::admin.households')->name('instance.households');
        Route::livewire('users', 'pages::admin.users')->name('instance.users');
        Route::livewire('registration', 'pages::admin.registration')->name('instance.registration');
    });
