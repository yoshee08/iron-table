<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\PlanSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The participant live screen (spec section 7.2). Scribes and observers see
 * the same screen. Only participants submit responses.
 */
class LiveController
{
    public function show(Request $request, Exercise $exercise): View
    {
        $role = $exercise->roleOf($request->user());
        abort_unless($role !== null || Gate::allows('moderate'), 403);

        return view('exercises.live', [
            'exercise' => $exercise,
            'participant' => $exercise->participants->firstWhere('user_id', $request->user()->id),
            'releases' => $exercise->releases()->with(['responses' => fn ($query) => $query->where('user_id', $request->user()->id)->with('citations')])->get(),
            'sections' => PlanSection::query()->where('ir_plan_id', $exercise->ir_plan_id)->orderBy('position')->get(),
            'canRespond' => $exercise->acceptsResponsesFrom($request->user()),
        ]);
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
