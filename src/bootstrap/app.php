<?php

use App\Http\Middleware\LimitGuests;
use App\Http\Middleware\RecordAudit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // YAML text is stored exactly as typed (scenario export without data loss).
        $middleware->trimStrings(except: ['source', 'profile']);

        // Spec SEC-06: the audit log records changes and downloads.
        // Spec SEC-05: a guest sees only the live screen of its own exercise.
        $middleware->web(append: [LimitGuests::class, RecordAudit::class]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Spec SEC-07. The "scheduler" service runs the schedule.
        $schedule->command('iron-table:retention')->dailyAt('03:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
