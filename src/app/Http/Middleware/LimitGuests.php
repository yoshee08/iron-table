<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Middleware;

use App\Models\Exercise;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A guest (spec SEC-05) sees only the live screen of its own exercise. When the
 * session ends, the guest access ends: the guest is logged out.
 */
class LimitGuests
{
    private const ALLOWED = [
        'exercises.live',
        'exercises.responses.store',
        'exercises.confirm',
        'exercises.display',
        'exercises.image',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->isGuest()) {
            return $next($request);
        }

        $exercise = Exercise::query()->find($user->guest_exercise_id);
        if ($exercise === null || $exercise->status === 'ended') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('join')->with('status', 'The session has ended. Thank you for taking part.');
        }

        // Live updates: the channel check allows only the guest's own exercise.
        if ($request->is('broadcasting/auth')) {
            return $next($request);
        }

        $route = $request->route();
        $name = is_object($route) ? $route->getName() : null;
        $target = is_object($route) ? $route->parameter('exercise') : null;
        if (in_array($name, self::ALLOWED, true) && ($name === 'logout' || ($target instanceof Exercise && $target->id === $exercise->id))) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            return redirect()->route('exercises.live', $exercise);
        }
        abort(403);
    }
}
