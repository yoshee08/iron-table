<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Enums\ExerciseRole;
use App\Jobs\GenerateScenarioImage;
use App\Models\Exercise;
use App\Models\IrPlan;
use App\Models\Participant;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ScenarioChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
        $images = ScenarioChecker::images($result['scenario']);
        foreach ($images as $name) {
            if (! Storage::exists(GenerateScenarioImage::folder($scenario->id).'/'.$name)) {
                return back()->withInput()->withErrors(['scenario_id' => "The picture $name of this scenario is missing. Upload it again."]);
            }
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
        // The exercise keeps its own copy of the pictures, like the scenario text.
        foreach ($images as $name) {
            Storage::copy(GenerateScenarioImage::folder($scenario->id).'/'.$name, self::folder($exercise).'/'.$name);
        }

        return redirect()->route('exercises.setup', $exercise)->with('status', 'Exercise prepared. Add the participants.');
    }

    public function setup(Exercise $exercise): View
    {
        return view('exercises.setup', [
            'exercise' => $exercise,
            'participants' => $exercise->participants()->with('user')->get(),
            'users' => User::query()->whereNull('guest_exercise_id')->whereNotIn('id', $exercise->participants()->select('user_id'))->orderBy('name')->get(),
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

    /**
     * Change the plan role of a participant, for example of a guest after the guest joined.
     */
    public function updateParticipant(Request $request, Exercise $exercise, Participant $participant): RedirectResponse
    {
        $data = $request->validate(['plan_role' => ['nullable', 'string', 'max:100']]);
        $participant->forceFill(['plan_role' => $data['plan_role'] ?? null])->save();
        ConsoleController::changed($exercise);

        return back()->with('status', 'Plan role saved.');
    }

    /**
     * Guest access with a session code (spec SEC-05): on makes a new code, off removes it.
     */
    public function guests(Exercise $exercise, string $action): RedirectResponse
    {
        abort_unless(in_array($action, ['on', 'off'], true), 404);
        if ($action === 'on' && $exercise->status === 'ended') {
            return back()->withErrors(['guests' => 'The session has ended. Guests cannot join.']);
        }
        $exercise->forceFill(['guest_code' => $action === 'on' ? Exercise::newGuestCode() : null])->save();

        return back()->with('status', $action === 'on' ? 'Guest access is on. Give the code to the guests.' : 'Guest access is off.');
    }

    public function removeParticipant(Exercise $exercise, Participant $participant): RedirectResponse
    {
        $participant->delete();

        return back()->with('status', 'Participant removed.');
    }

    /**
     * The private storage folder of the pictures of an exercise.
     */
    public static function folder(Exercise $exercise): string
    {
        return 'exercise-images/'.$exercise->id;
    }

    public function destroy(Exercise $exercise): RedirectResponse
    {
        $exercise->purge();

        return redirect()->route('exercises.index')->with('status', 'Exercise deleted.');
    }
}
