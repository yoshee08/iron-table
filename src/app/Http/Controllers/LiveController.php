<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Enums\ExerciseRole;
use App\Models\Exercise;
use App\Models\InjectRelease;
use App\Models\PlanSection;
use App\Services\ScenarioChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The participant live screen (spec section 7.2). Scribes and observers see
 * the same screen. Only participants submit responses.
 */
class LiveController
{
    public function show(Request $request, Exercise $exercise): View
    {
        $role = $exercise->roleOf($request->user());
        $moderator = Gate::allows('moderate');
        abort_unless($role !== null || $moderator, 403);
        $participant = $exercise->participants->firstWhere('user_id', $request->user()->id);

        return view('exercises.live', [
            'exercise' => $exercise,
            'participant' => $participant,
            'releases' => $exercise->releases()
                ->with(['responses' => fn ($query) => $query->where('user_id', $request->user()->id)->with('citations')])->get()
                ->filter(fn (InjectRelease $release) => $release->isVisibleTo($participant, $moderator))->values(),
            'sections' => PlanSection::query()->where('ir_plan_id', $exercise->ir_plan_id)->orderBy('position')->get(),
            'canRespond' => $exercise->acceptsResponsesFrom($request->user()),
            // Scribes write and read the logs here (spec section 7.3). Moderator notes stay on the console.
            'logs' => $role === ExerciseRole::Scribe ? $exercise->logEntries()->where('type', '!=', 'note')->with('recorder')->get() : null,
        ]);
    }

    /**
     * The participant confirms the plan role at the briefing (spec section 4.2).
     */
    public function confirm(Request $request, Exercise $exercise): RedirectResponse
    {
        $participant = $exercise->participants->firstWhere('user_id', $request->user()->id);
        abort_if($participant === null, 403);

        $participant->forceFill(['confirmed_at' => now()])->save();
        ConsoleController::changed($exercise);

        return back()->with('status', 'Role confirmed.');
    }

    /**
     * The shared display for in-room sessions (spec section 7.5): the newest inject for
     * everybody, its artifacts and the simulated clock. Never notes, responses or the rubric.
     */
    public function display(Request $request, Exercise $exercise): View
    {
        abort_unless($exercise->roleOf($request->user()) !== null || Gate::allows('moderate'), 403);

        return view('exercises.display', [
            'exercise' => $exercise,
            'release' => $exercise->releases()->get()->first(fn (InjectRelease $release) => $release->isForAll()),
        ]);
    }

    /**
     * A picture of the exercise (a copy made when the exercise was prepared).
     */
    public function image(Request $request, Exercise $exercise, string $name): StreamedResponse
    {
        abort_unless($exercise->roleOf($request->user()) !== null || Gate::allows('moderate'), 403);
        $path = ExerciseController::folder($exercise).'/'.$name;
        abort_unless(preg_match(ScenarioChecker::IMAGE_NAME, $name) && Storage::exists($path), 404);

        return Storage::response($path);
    }

    public function respond(Request $request, Exercise $exercise): RedirectResponse
    {
        abort_unless($exercise->acceptsResponsesFrom($request->user()), 403);

        $data = $request->validate([
            'inject_release_id' => ['required', Rule::exists('inject_releases', 'id')->where('exercise_id', $exercise->id)],
            'body' => ['required', 'string', 'max:5000'],
            'sections' => ['array'],
            'sections.*' => ['integer', Rule::exists('plan_sections', 'id')->where('ir_plan_id', $exercise->ir_plan_id)],
            'no_coverage' => ['boolean'],
        ]);

        $release = InjectRelease::query()->findOrFail($data['inject_release_id']);
        abort_unless($release->isVisibleTo($exercise->participants->firstWhere('user_id', $request->user()->id), false), 403);

        $response = $exercise->responses()->forceCreate([
            'inject_release_id' => $data['inject_release_id'],
            'user_id' => $request->user()->id,
            'plan_role' => $exercise->participants->firstWhere('user_id', $request->user()->id)?->plan_role,
            'body' => $data['body'],
            'no_coverage' => $data['no_coverage'] ?? false,
            'sim_day' => $exercise->sim_day,
            'sim_time' => $exercise->sim_time ?? '00:00',
        ]);
        $response->citations()->attach($data['sections'] ?? []);
        ConsoleController::changed($exercise);

        return redirect()->to(route('exercises.live', $exercise).'#release-'.$data['inject_release_id'])->with('status', 'Response sent.');
    }
}
