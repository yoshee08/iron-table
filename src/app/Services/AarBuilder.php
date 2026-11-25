<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Http\Controllers\ReviewController;
use App\Models\Exercise;
use App\Models\Finding;
use App\Models\ObjectiveRating;
use App\Models\PlanSection;
use App\Models\Report;
use App\Models\RubricMark;
use App\Models\User;

/**
 * Builds the after-action report (spec section 8.4) from the exercise records and
 * the text that the moderator writes (scenario summary and one paragraph for each
 * IR phase). A final report stores the result, so it never changes.
 */
class AarBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Exercise $exercise, Report $report, ?User $approver = null): array
    {
        $exercise->load(['marks', 'ratings', 'findings', 'participants.user', 'plan']);
        $scores = $exercise->scores();
        $coverage = ReviewController::coverage($exercise);
        $text = $report->content;
        $sections = fn (array $list) => array_map(fn (PlanSection $section) => trim($section->number.' '.$section->title), $list);
        $rubric = $exercise->scenario['rubric'] ?? [];

        return [
            'title' => $exercise->title,
            'version' => $report->version,
            'tlp' => $report->tlp,
            'information' => [
                'Date' => $exercise->started_at?->toDateString() ?? 'Not run',
                'Scenario' => ($exercise->scenario['meta']['title'] ?? '').' (version '.$exercise->scenario_version.')',
                'IR plan' => $exercise->plan ? $exercise->plan->title.' (version '.$exercise->plan->version.')' : 'None',
            ],
            'participants' => $exercise->participants->map(fn ($participant) => [
                'name' => $participant->user->name,
                'role' => $participant->role->label(),
                'plan_role' => $participant->plan_role ?? '',
            ])->all(),
            'summary' => $text['summary'] ?? ($exercise->scenario['briefing'] ?? ''),
            'phases' => collect($scores['phases'])->map(fn (array $score, string $phase) => $score + [
                'phase' => ucfirst($phase),
                'text' => $text['phases'][$phase] ?? '',
            ])->values()->all(),
            'score' => $scores,
            'changed_scores' => $exercise->marks->whereNotNull('reason')->map(fn (RubricMark $mark) => [
                'item' => $rubric[$mark->inject_key][$mark->item]['item'] ?? $mark->inject_key,
                'points' => $mark->points,
                'reason' => $mark->reason,
            ])->values()->all(),
            'objectives' => collect($exercise->scenario['objectives'] ?? [])->map(fn ($objective, $n) => [
                'objective' => $objective,
                'rating' => ObjectiveRating::RATINGS[$exercise->ratings->firstWhere('objective', $n)?->rating] ?? 'Not rated',
                'justification' => $exercise->ratings->firstWhere('objective', $n)?->justification ?? '',
            ])->all(),
            'coverage' => [
                'Used' => $sections($coverage['used']),
                'Missed' => $sections($coverage['missed']),
                'Extra' => $sections($coverage['extra']),
                'gaps' => $coverage['gaps'],
            ],
            'strengths' => $exercise->findings->where('type', 'strength')->pluck('text')->values()->all(),
            'weaknesses' => $exercise->findings->whereIn('type', ['weakness', 'plan_gap'])
                ->map(fn (Finding $finding) => Finding::TYPES[$finding->type].': '.$finding->text)->values()->all(),
            'actions' => $exercise->findings->whereNotNull('action')->map(fn (Finding $finding) => [
                'control' => $finding->control ?? '',
                'action' => $finding->action,
                'owner' => $finding->owner,
                'due' => $finding->due_date?->toDateString(),
                'documents' => $finding->documents ?? '',
                'status' => $finding->status,
            ])->values()->all(),
            'closure' => [
                'approver' => $approver?->name,
                'date' => $approver ? now()->toDateString() : null,
                'score' => $scores['percent'],
            ],
        ];
    }
}
