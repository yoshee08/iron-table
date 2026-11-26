<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AiScenarioController;
use App\Http\Controllers\ConsoleController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanProfileController;
use App\Http\Controllers\PlanSectionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ScenarioController;
use App\Http\Controllers\ScenarioImageController;
use App\Http\Middleware\RequireTwoFactorForStaff;
use Illuminate\Support\Facades\Route;

// Login, logout, password reset and two-factor routes come from Laravel Fortify.

Route::redirect('/', '/dashboard');

// Guests join an exercise with its session code (spec SEC-05).
Route::middleware('guest')->group(function () {
    Route::get('/join', [JoinController::class, 'show'])->name('join');
    Route::post('/join', [JoinController::class, 'store'])->middleware('throttle:10,1')->name('join.store');
});

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
            // AI drafting (spec section 9): only when AI_ENABLED=true.
            Route::middleware('can:use-ai')->group(function () {
                Route::get('/scenarios/draft', [AiScenarioController::class, 'create'])->name('scenarios.draft');
                Route::post('/scenarios/draft', [AiScenarioController::class, 'store'])->name('scenarios.draft.store');
                Route::post('/scenarios/{scenario}/ai/{inject}/{step}', [AiScenarioController::class, 'step'])->name('scenarios.ai');
            });
            Route::post('/scenarios', [ScenarioController::class, 'store'])->name('scenarios.store');
            Route::get('/scenarios/{scenario}', [ScenarioController::class, 'show'])->name('scenarios.show');
            Route::get('/scenarios/{scenario}/edit', [ScenarioController::class, 'edit'])->name('scenarios.edit');
            Route::put('/scenarios/{scenario}', [ScenarioController::class, 'update'])->name('scenarios.update');
            Route::get('/scenarios/{scenario}/download', [ScenarioController::class, 'download'])->name('scenarios.download');
            Route::delete('/scenarios/{scenario}', [ScenarioController::class, 'destroy'])->name('scenarios.destroy');

            // Picture artifacts: an AI prompt by default, or an upload.
            Route::post('/scenarios/{scenario}/pictures/{inject}', [ScenarioImageController::class, 'add'])->name('scenarios.pictures.add');
            Route::post('/scenarios/{scenario}/pictures/{inject}/{artifact}/upload', [ScenarioImageController::class, 'upload'])->whereNumber('artifact')->name('scenarios.pictures.upload');
            Route::post('/scenarios/{scenario}/pictures/{inject}/{artifact}/generate', [ScenarioImageController::class, 'generate'])->whereNumber('artifact')->middleware('can:use-images')->name('scenarios.pictures.generate');
            Route::post('/scenarios/{scenario}/images/{image}/choose', [ScenarioImageController::class, 'choose'])->name('scenarios.images.choose');
            Route::get('/scenarios/{scenario}/images/{name}', [ScenarioImageController::class, 'file'])->name('scenarios.image');

            // Exercises: preparation and the moderator console (spec sections 4 and 7.1).
            Route::get('/exercises/create', [ExerciseController::class, 'create'])->name('exercises.create');
            Route::post('/exercises', [ExerciseController::class, 'store'])->name('exercises.store');
            Route::get('/exercises/{exercise}/setup', [ExerciseController::class, 'setup'])->name('exercises.setup');
            Route::post('/exercises/{exercise}/participants', [ExerciseController::class, 'addParticipant'])->name('exercises.participants.store');
            Route::put('/exercises/{exercise}/participants/{participant}', [ExerciseController::class, 'updateParticipant'])->scopeBindings()->name('exercises.participants.update');
            Route::delete('/exercises/{exercise}/participants/{participant}', [ExerciseController::class, 'removeParticipant'])->scopeBindings()->name('exercises.participants.destroy');
            Route::post('/exercises/{exercise}/guests/{action}', [ExerciseController::class, 'guests'])->name('exercises.guests');
            Route::delete('/exercises/{exercise}', [ExerciseController::class, 'destroy'])->name('exercises.destroy');
            Route::get('/exercises/{exercise}/control', [ConsoleController::class, 'show'])->name('exercises.control');
            Route::post('/exercises/{exercise}/session/{action}', [ConsoleController::class, 'session'])->name('exercises.session');
            Route::post('/exercises/{exercise}/release', [ConsoleController::class, 'release'])->name('exercises.release');
            Route::post('/exercises/{exercise}/clock', [ConsoleController::class, 'clock'])->name('exercises.clock');
            Route::post('/exercises/{exercise}/write', [ConsoleController::class, 'write'])->name('exercises.write');

            // Review: debrief scores, ratings and findings (spec sections 4.4 and 8.3).
            Route::get('/exercises/{exercise}/review', [ReviewController::class, 'show'])->name('exercises.review');
            Route::post('/exercises/{exercise}/review/marks', [ReviewController::class, 'mark'])->name('exercises.review.marks');
            Route::post('/exercises/{exercise}/review/objectives/{objective}', [ReviewController::class, 'rate'])->whereNumber('objective')->name('exercises.review.rate');
            Route::post('/exercises/{exercise}/findings', [ReviewController::class, 'addFinding'])->name('exercises.findings.store');
            Route::put('/exercises/{exercise}/findings/{finding}', [ReviewController::class, 'updateFinding'])->scopeBindings()->name('exercises.findings.update');
            Route::delete('/exercises/{exercise}/findings/{finding}', [ReviewController::class, 'deleteFinding'])->scopeBindings()->name('exercises.findings.destroy');

            // After-action report and evidence export (spec sections 4.5, 8.4 and 8.5).
            Route::get('/exercises/{exercise}/report', [ReportController::class, 'show'])->name('exercises.report');
            Route::post('/exercises/{exercise}/report', [ReportController::class, 'save'])->name('exercises.report.save');
            Route::post('/exercises/{exercise}/report/final', [ReportController::class, 'finalize'])->name('exercises.report.final');
            Route::post('/exercises/{exercise}/report/new-version', [ReportController::class, 'newVersion'])->name('exercises.report.new');
            Route::get('/exercises/{exercise}/reports/{report}/aar.pdf', [ReportController::class, 'pdf'])->scopeBindings()->name('exercises.report.pdf');
            Route::get('/exercises/{exercise}/reports/{report}/evidence.zip', [ReportController::class, 'export'])->scopeBindings()->name('exercises.report.export');
        });
        Route::get('/plans/{plan}', [PlanController::class, 'show'])->name('plans.show');

        // Exercises: every user sees the exercises that they are in (spec section 7.2).
        Route::get('/exercises', [ExerciseController::class, 'index'])->name('exercises.index');
        Route::get('/exercises/{exercise}/live', [LiveController::class, 'show'])->name('exercises.live');
        Route::post('/exercises/{exercise}/responses', [LiveController::class, 'respond'])->name('exercises.responses.store');
        Route::post('/exercises/{exercise}/confirm', [LiveController::class, 'confirm'])->name('exercises.confirm');
        Route::get('/exercises/{exercise}/display', [LiveController::class, 'display'])->name('exercises.display');
        Route::get('/exercises/{exercise}/images/{name}', [LiveController::class, 'image'])->name('exercises.image');
        Route::post('/exercises/{exercise}/logs/{type}', [LogController::class, 'store'])->name('exercises.logs.store');
        Route::get('/plans/{plan}/download', [PlanController::class, 'download'])->name('plans.download');

        Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
            Route::post('/users/{user}/setup-link', [UserController::class, 'setupLink'])->name('users.setup-link');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        });
    });
});
