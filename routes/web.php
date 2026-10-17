<?php

use App\Http\Controllers\Accounts\PasskeyChallengeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Platform/Home')->name('home');

// SPIKE (#13): a page to manage factors, and a passkey as second factor.
Route::get('/dashboard', function (Request $request) {
    $user = $request->user();

    return inertia('Accounts/Security', [
        'user' => ['name' => $user->name, 'email' => $user->email],
        'passkeys' => $user->passkeys()->get(['id', 'name', 'created_at']),
        'totp' => [
            'enabled' => $user->two_factor_secret !== null,
            'confirmed' => $user->two_factor_confirmed_at !== null,
            'qrSvg' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null ? $user->twoFactorQrCodeSvg() : null,
        ],
    ]);
})->middleware('auth')->name('dashboard');

Route::middleware(['guest', 'throttle:two-factor'])->group(function () {
    Route::get('/two-factor-challenge/passkey/options', [PasskeyChallengeController::class, 'options'])->name('two-factor.passkey.options');
    Route::post('/two-factor-challenge/passkey', [PasskeyChallengeController::class, 'store'])->name('two-factor.passkey');
});
