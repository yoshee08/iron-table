<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Events\ExerciseChanged;
use App\Models\Exercise;
use App\Services\ScenarioChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The moderator console (spec sections 4.2 to 4.4 and 7.1): session controls,
 * the two clocks, the timeline and the release of injects.
 */
class ConsoleController
{
    /** Allowed status changes: action => [from statuses, to status]. */
    private const ACTIONS = [
        'start' => [['prepared'], 'running'],
        'pause' => [['running'], 'paused'],
        'resume' => [['paused', 'stopped'], 'running'],
        'stop' => [['running', 'paused'], 'stopped'],
        'end' => [Exercise::LIVE, 'ended'],
    ];

    public function show(Exercise $exercise): View
    {
        $injects = $exercise->scenario['injects'];
        $released = $exercise->releases()->with(['responses.user', 'responses.citations'])->get();
        $last = array_search($released->first()?->inject_key, array_column($injects, 'id'), true);
        $suggested = $last === false
            ? [$injects[0]['id']]
            : array_column(ScenarioChecker::following($injects, $last), 0);

        return view('exercises.control', [
            'exercise' => $exercise,
            'injects' => $injects,
            'released' => $released,
            'releasedKeys' => $released->pluck('inject_key')->all(),
            'suggested' => $suggested,
            'participants' => $exercise->participants()->with('user')->get(),
        ]);
    }

    public function session(Exercise $exercise, string $action): RedirectResponse
    {
        abort_unless(isset(self::ACTIONS[$action]), 404);
        [$from, $to] = self::ACTIONS[$action];
        if (! in_array($exercise->status, $from, true)) {
            return back()->withErrors(['session' => "You cannot $action the session when its status is {$exercise->status}."]);
        }

        $exercise->status = $to;
        if ($action === 'start') {
            $first = $exercise->scenario['injects'][0];
            $exercise->forceFill(['started_at' => now(), 'sim_day' => $first['day'] ?? 1, 'sim_time' => $first['at']]);
        }
        if ($action === 'end') {
            $exercise->ended_at = now();
        }
        $exercise->save();
        self::changed($exercise);

        return back()->with('status', 'Session status: '.$to.'.');
    }

    /**
     * Release a prepared inject: the next one, a branch, or one out of order (spec section 4.3, step 4).
     */
    public function release(Request $request, Exercise $exercise): RedirectResponse
    {
        $injects = collect($exercise->scenario['injects']);
        $key = (string) $request->input('inject');
        $inject = $injects->firstWhere('id', $key);
        abort_if($inject === null, 404);

        if ($exercise->status !== 'running') {
            return back()->withErrors(['session' => 'Start or resume the session before you release an inject.']);
        }
        if ($exercise->releases()->where('inject_key', $key)->exists()) {
            return back()->withErrors(['session' => "Inject $key is already released."]);
        }

        // The simulated clock moves forward to the time of the inject. It never goes back.
        $day = $inject['day'] ?? 1;
        if (Exercise::minutes($day, $inject['at']) > Exercise::minutes($exercise->sim_day, $exercise->sim_time ?? '00:00')) {
            $exercise->forceFill(['sim_day' => $day, 'sim_time' => $inject['at']])->save();
        }

        $exercise->releases()->forceCreate([
            'inject_key' => $key,
            'title' => $inject['title'],
            'body' => (string) ($inject['body'] ?? ''),
            'artifacts' => $inject['artifacts'] ?? [],
            'questions' => $inject['questions'] ?? [],
            'sim_day' => $exercise->sim_day,
            'sim_time' => $exercise->sim_time,
            'released_by' => $request->user()->id,
        ]);
        self::changed($exercise);

        return back()->with('status', "Inject $key released.");
    }

    /**
     * The moderator moves the simulated clock forward (spec section 8.1).
     */
    public function clock(Request $request, Exercise $exercise): RedirectResponse
    {
        $data = $request->validate([
            'sim_day' => ['required', 'integer', 'min:1', 'max:30'],
            'sim_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);
        if ($exercise->sim_time !== null
            && Exercise::minutes($data['sim_day'], $data['sim_time']) < Exercise::minutes($exercise->sim_day, $exercise->sim_time)) {
            return back()->withErrors(['sim_time' => 'The simulated clock can only move forward.']);
        }

        $exercise->forceFill($data)->save();
        self::changed($exercise);

        return back()->with('status', 'Simulated clock: '.$exercise->simClock().'.');
    }

    /**
     * Tell open pages to reload their live part. A failed broadcast must not stop the moderator.
     */
    public static function changed(Exercise $exercise): void
    {
        rescue(fn () => event(new ExerciseChanged($exercise->id)));
    }
}
