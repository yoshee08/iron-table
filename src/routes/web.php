<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Support\Facades\Route;

// Login, logout, password reset and two-factor routes come from Laravel Fortify.

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/account', 'account')->name('account');
});
