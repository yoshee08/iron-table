<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Enums\ExerciseRole;
use App\Enums\Role;
use App\Models\Exercise;
use App\Models\User;
use App\Services\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * A guest joins an exercise with its session code (spec section 3 and SEC-05).
 * The guest gets a temporary account for that exercise only: the live screen,
 * live updates and the audit log then work as for other participants.
 */
class JoinController
{
    public function show(Request $request): View
    {
        return view('join', ['code' => (string) $request->query('code', '')]);
    }

    public function store(Request $request, AuditLog $audit): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
        ]);
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $data['code']));
        $exercise = $code === '' ? null : Exercise::query()->where('guest_code', $code)->first();

        if ($exercise === null || $exercise->status === 'ended') {
            return back()->withInput($request->only('name'))
                ->withErrors(['code' => 'This session code is not valid. Ask the moderator for the code.']);
        }

        $user = DB::transaction(function () use ($exercise, $data) {
            $user = User::query()->forceCreate([
                'name' => $data['name'],
                'email' => 'guest-'.Str::lower(Str::random(20)).'@guest.invalid',
                'password' => Str::random(64),
                'role' => Role::User,
                'guest_exercise_id' => $exercise->id,
            ]);
            $exercise->participants()->forceCreate(['user_id' => $user->id, 'role' => ExerciseRole::Participant]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $audit->record('guest.join', ['exercise' => $exercise->id], $user);
        ConsoleController::changed($exercise);

        return redirect()->route('exercises.live', $exercise);
    }
}
