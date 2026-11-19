<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Enums\ExerciseRole;
use App\Models\Exercise;
use App\Models\IrPlan;
use App\Models\Participant;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ScenarioChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Exercises: preparation (spec section 4.1, steps 6 and 7). Moderators make an
 * exercise from a scenario that passes the consistency check, then add the
 * participants and their plan roles.
 */
class ExerciseController
{
    public function index(Request $request): View
    {
        $query = Exercise::query()->orderByDesc('id');
        if (! Gate::allows('moderate')) {
            $query->whereHas('participants', fn ($participants) => $participants->where('user_id', $request->user()->id));
        }

        return view('exercises.index', ['exercises' => $query->get()]);
    }

    public function create(): View
    {
        return view('exercises.create', ['scenarios' => Scenario::query()->orderBy('title')->get()]);
    }

    public function store(Request $request, ScenarioChecker $checker): RedirectResponse
    {
        $data = $request->validate([
            'scenario_id' => ['required', 'exists:scenarios,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);
        $scenario = Scenario::query()->findOrFail($data['scenario_id']);
        $plan = IrPlan::current();

        // The consistency check must pass before a session can start (spec section 6.3).
        $result = $checker->check($checker->read($scenario->source), $plan?->profileValues() ?? [], $plan?->concepts());
        if ($result['errors'] !== []) {
            return back()->withInput()->withErrors(['scenario_id' => 'The consistency check found '.count($result['errors']).' errors in this scenario. Correct them first.']);
        }

        $exercise = new Exercise;
        $exercise->forceFill([
            'title' => ($data['title'] ?? '') ?: $scenario->title,
            'scenario_id' => $scenario->id,
            'scenario_version' => $scenario->version,
            'ir_plan_id' => $plan?->id,
            'scenario' => $result['scenario'],
            'created_by' => $request->user()->id,
        ])->save();

        return redirect()->route('exercises.setup', $exercise)->with('status', 'Exercise prepared. Add the participants.');
    }

    public function setup(Exercise $exercise): View
    {
        return view('exercises.setup', [
            'exercise' => $exercise,
            'participants' => $exercise->participants()->with('user')->get(),
            'users' => User::query()->whereNotIn('id', $exercise->participants()->select('user_id'))->orderBy('name')->get(),
        ]);
    }

    public function addParticipant(Request $request, Exercise $exercise): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id', Rule::unique('participants')->where('exercise_id', $exercise->id)],
            'role' => ['required', Rule::enum(ExerciseRole::class)],
            'plan_role' => ['nullable', 'string', 'max:100'],
        ]);

        $exercise->participants()->forceCreate($data);

        return back()->with('status', 'Participant added.');
    }

    public function removeParticipant(Exercise $exercise, Participant $participant): RedirectResponse
    {
        $participant->delete();

        return back()->with('status', 'Participant removed.');
    }

    public function destroy(Exercise $exercise): RedirectResponse
    {
        $exercise->delete();

        return redirect()->route('exercises.index')->with('status', 'Exercise deleted.');
    }
}
