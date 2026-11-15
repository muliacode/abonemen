<?php

use App\Http\Middleware\EnsureInstanceAdmin;
use App\Http\Middleware\RequireSecondFactor;
use App\Support\AdminPath;
use Illuminate\Support\Facades\Route;

/*
| The Instance Admin section. It sits under a configurable path (ABONEMEN_ADMIN_PATH), so that an instance
| need not use the one everybody tries first, and it is a 404 for everyone who is not an Instance Admin.
| The doors, in order: 404 for outsiders (so a password prompt never shows that the section exists), a second
| factor, then a password confirmation, as for Settings > Security.
*/
AdminPath::assertFree(config('abonemen.admin_path'), app('router'));

Route::prefix(config('abonemen.admin_path'))
    ->middleware([EnsureInstanceAdmin::class, 'verified', RequireSecondFactor::class, 'password.confirm'])
    ->group(function () {
        Route::livewire('/', 'pages::admin.households')->name('instance.households');
        Route::livewire('users', 'pages::admin.users')->name('instance.users');
        Route::livewire('general', 'pages::admin.general')->name('instance.general');
        Route::livewire('email', 'pages::admin.email')->name('instance.email');
        Route::livewire('server', 'pages::admin.server')->name('instance.server');
    });
