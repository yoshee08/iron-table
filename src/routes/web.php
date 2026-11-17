<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\RequireTwoFactorForStaff;
use Illuminate\Support\Facades\Route;

// Login, logout, password reset and two-factor routes come from Laravel Fortify.

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    // Always open, so that a user can turn on two-factor authentication.
    Route::view('/account', 'account')->name('account');

    Route::middleware(RequireTwoFactorForStaff::class)->group(function () {
        Route::view('/dashboard', 'dashboard')->name('dashboard');

        Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
            Route::post('/users/{user}/setup-link', [UserController::class, 'setupLink'])->name('users.setup-link');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });
    });
});
