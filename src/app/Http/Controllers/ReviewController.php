<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Finding;
use App\Models\ObjectiveRating;
use App\Models\PlanSection;
use App\Models\RubricMark;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The Review screen (spec sections 4.4 and 8.3, backlog B4, B5 and B11): rubric
 * scores, objective ratings, plan coverage, the parking lot, and findings with
 * corrective actions.
 */
class ReviewController
{
    public function show(Exercise $exercise): View
    {
        $exercise->load(['marks', 'ratings', 'findings']);

        return view('exercises.review', [
            'exercise' => $exercise,
            'marks' => $exercise->marks->keyBy(fn (RubricMark $mark) => $mark->inject_key.'#'.$mark->item),
            'ratings' => $exercise->ratings->keyBy('objective'),
            'scores' => $exercise->scores(),
            'coverage' => self::coverage($exercise),
            'parking' => $exercise->logEntries()->where('type', 'parking')->get(),
        ]);
    }

    /**
     * Save the marks of all rubric items. A score that is not the usual score needs a reason.
     */
    public function mark(Request $request, Exercise $exercise): RedirectResponse
    {
        $data = $request->validate([
            'marks' => ['array'],
            'marks.*.*.result' => ['nullable', Rule::in(array_keys(RubricMark::RESULTS))],
            'marks.*.*.points' => ['nullable', 'integer', 'min:0'],
            'marks.*.*.reason' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($data['marks'] ?? [] as $injectKey => $items) {
            foreach ($items as $n => $mark) {
                $item = $exercise->scenario['rubric'][$injectKey][$n] ?? null;
                if ($item === null || empty($mark['result'])) {
                    continue;
                }
                $usual = RubricMark::pointsFor($mark['result'], $item['points']);
                $points = isset($mark['points']) ? min((int) $mark['points'], $item['points']) : $usual;
                if ($points !== $usual && blank($mark['reason'] ?? null)) {
                    throw ValidationException::withMessages(["marks.$injectKey.$n.reason" => "Write a reason for the changed score of \"{$item['item']}\"."]);
                }

                RubricMark::query()->updateOrCreate(
                    ['exercise_id' => $exercise->id, 'inject_key' => (string) $injectKey, 'item' => (int) $n],
                    ['result' => $mark['result'], 'points' => $points, 'reason' => $points !== $usual ? $mark['reason'] : null, 'marked_by' => $request->user()->id],
                );
            }
        }

        return redirect()->to(route('exercises.review', $exercise).'#scores')->with('status', 'Scores saved.');
    }

    public function rate(Request $request, Exercise $exercise, int $objective): RedirectResponse
    {
        abort_unless(isset($exercise->scenario['objectives'][$objective]), 404);
        $data = $request->validate([
            'rating' => ['required', Rule::in(array_keys(ObjectiveRating::RATINGS))],
            'justification' => ['required', 'string', 'max:2000'],
        ]);

        ObjectiveRating::query()->updateOrCreate(
            ['exercise_id' => $exercise->id, 'objective' => $objective],
            $data + ['rated_by' => $request->user()->id],
        );

        return redirect()->to(route('exercises.review', $exercise).'#objectives')->with('status', 'Rating saved.');
    }

    public function addFinding(Request $request, Exercise $exercise): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Finding::TYPES))],
            'text' => ['required', 'string', 'max:2000'],
            'control' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:2000'],
            'owner' => ['nullable', 'string', 'max:255', 'required_with:action'],
            'due_date' => ['nullable', 'date', 'required_with:action'],
            'documents' => ['nullable', 'string', 'max:255'],
        ]);

        $exercise->findings()->forceCreate($data + ['created_by' => $request->user()->id]);

        return redirect()->to(route('exercises.review', $exercise).'#findings')->with('status', 'Finding saved.');
    }

    public function updateFinding(Request $request, Exercise $exercise, Finding $finding): RedirectResponse
    {
        $finding->forceFill($request->validate(['status' => ['required', Rule::in(['open', 'done'])]]))->save();

        return redirect()->to(route('exercises.review', $exercise).'#findings')->with('status', 'Finding updated.');
    }

    public function deleteFinding(Exercise $exercise, Finding $finding): RedirectResponse
    {
        $finding->delete();

        return redirect()->to(route('exercises.review', $exercise).'#findings')->with('status', 'Finding deleted.');
    }

    /**
     * Plan coverage of the whole exercise (spec section 8.4): expected sections that the
     * responses cite (used) or do not cite (missed), cited sections that no inject expects
     * (extra), and responses without plan coverage (gaps).
     *
     * @return array{used: list<PlanSection>, missed: list<PlanSection>, extra: list<PlanSection>, gaps: int}
     */
    public static function coverage(Exercise $exercise): array
    {
        $releases = $exercise->releases()->with('responses.citations')->get();
        $injects = collect($exercise->scenario['injects'])->keyBy('id');
        // Injects written during play (new-1, ...) have no expected plan concepts.
        $concepts = $releases->flatMap(fn ($release) => $injects->get($release->inject_key)['expected_plan'] ?? [])->unique();
        $expected = PlanSection::query()->where('ir_plan_id', $exercise->ir_plan_id)->whereIn('concept', $concepts)->orderBy('position')->get();
        $cited = $releases->flatMap->responses->flatMap->citations->unique('id')->sortBy('position');

        return [
            'used' => $expected->whereIn('id', $cited->pluck('id'))->values()->all(),
            'missed' => $expected->whereNotIn('id', $cited->pluck('id'))->values()->all(),
            'extra' => $cited->whereNotIn('id', $expected->pluck('id'))->values()->all(),
            'gaps' => $releases->flatMap->responses->where('no_coverage', true)->count(),
        ];
    }
}
