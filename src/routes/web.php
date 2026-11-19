<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanProfileController;
use App\Http\Controllers\PlanSectionController;
use App\Http\Controllers\ScenarioController;
use App\Http\Middleware\RequireTwoFactorForStaff;
use Illuminate\Support\Facades\Route;

// Login, logout, password reset and two-factor routes come from Laravel Fortify.

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    // Always open, so that a user can turn on two-factor authentication.
    Route::view('/account', 'account')->name('account');

    Route::middleware(RequireTwoFactorForStaff::class)->group(function () {
        Route::view('/dashboard', 'dashboard')->name('dashboard');

        // The IR plan: every user reads it; moderators and administrators change it.
        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::middleware('can:moderate')->group(function () {
            Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
            Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
            Route::scopeBindings()->group(function () {
                Route::get('/plans/{plan}/sections/{section}/edit', [PlanSectionController::class, 'edit'])->name('plans.sections.edit');
                Route::put('/plans/{plan}/sections/{section}', [PlanSectionController::class, 'update'])->name('plans.sections.update');
                Route::delete('/plans/{plan}/sections/{section}', [PlanSectionController::class, 'destroy'])->name('plans.sections.destroy');
            });
            Route::get('/plans/{plan}/profile', [PlanProfileController::class, 'edit'])->name('plans.profile.edit');
            Route::put('/plans/{plan}/profile', [PlanProfileController::class, 'update'])->name('plans.profile.update');

            // Scenarios: moderators and administrators only, so that participants do not see the injects first.
            Route::get('/scenarios', [ScenarioController::class, 'index'])->name('scenarios.index');
            Route::get('/scenarios/create', [ScenarioController::class, 'create'])->name('scenarios.create');
            Route::get('/scenarios/example', [ScenarioController::class, 'example'])->name('scenarios.example');
            Route::post('/scenarios', [ScenarioController::class, 'store'])->name('scenarios.store');
            Route::get('/scenarios/{scenario}', [ScenarioController::class, 'show'])->name('scenarios.show');
            Route::get('/scenarios/{scenario}/edit', [ScenarioController::class, 'edit'])->name('scenarios.edit');
            Route::put('/scenarios/{scenario}', [ScenarioController::class, 'update'])->name('scenarios.update');
            Route::get('/scenarios/{scenario}/download', [ScenarioController::class, 'download'])->name('scenarios.download');
            Route::delete('/scenarios/{scenario}', [ScenarioController::class, 'destroy'])->name('scenarios.destroy');
        });
        Route::get('/plans/{plan}', [PlanController::class, 'show'])->name('plans.show');
        Route::get('/plans/{plan}/download', [PlanController::class, 'download'])->name('plans.download');

        Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
            Route::post('/users/{user}/setup-link', [UserController::class, 'setupLink'])->name('users.setup-link');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        });
    });
});
