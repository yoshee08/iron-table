<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Enums\ExerciseRole;
use App\Models\Exercise;
use App\Models\LogEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Log entries (spec sections 7.3 and 8.2). Scribes and moderators write the
 * three logs and the parking lot. Only moderators write notes. The times come
 * from the server and the simulated clock.
 */
class LogController
{
    public function store(Request $request, Exercise $exercise, string $type): RedirectResponse
    {
        abort_unless(isset(LogEntry::TYPES[$type]), 404);
        $moderator = Gate::allows('moderate');
        abort_unless($moderator || ($type !== 'note' && $exercise->roleOf($request->user()) === ExerciseRole::Scribe), 403);
        abort_unless(in_array($exercise->status, Exercise::LIVE, true), 403);

        $rules = [];
        foreach (LogEntry::TYPES[$type]['fields'] as $name => $field) {
            $rules[$name] = is_array($field) ? ['nullable', Rule::in($field[1])] : ['nullable', 'string', 'max:2000'];
        }
        $fields = array_filter($request->validate($rules), fn ($value) => filled($value));
        if ($fields === []) {
            return back()->withErrors(['log' => 'Fill in at least one field.']);
        }

        $exercise->logEntries()->forceCreate([
            'type' => $type,
            'fields' => $fields,
            'sim_day' => $exercise->sim_day,
            'sim_time' => $exercise->sim_time ?? '00:00',
            'recorded_by' => $request->user()->id,
        ]);
        ConsoleController::changed($exercise);

        return back()->with('status', LogEntry::TYPES[$type]['label'].': entry saved.');
    }
}
