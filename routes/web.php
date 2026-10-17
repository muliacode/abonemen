<?php

use App\Http\Controllers\Crypto\SpikeServerCheckController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Platform/Home')->name('home');

// SPIKE (#15): the shared-hosting check page, behind SPIKE_TOKEN.
Route::get('/spike/check', [SpikeServerCheckController::class, 'show'])->name('spike.check');
Route::post('/spike/check/queue', [SpikeServerCheckController::class, 'fillQueue'])->name('spike.check.queue');
Route::post('/spike/check/prepare', [SpikeServerCheckController::class, 'prepare'])->name('spike.check.prepare');
