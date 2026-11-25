<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Report;
use App\Services\AarBuilder;
use App\Services\EvidenceExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The after-action report (spec sections 4.5, 8.4 and 8.5; backlog B6 and B8).
 * The moderator edits a draft, then makes it final. A final version never changes:
 * a later change makes a new version.
 */
class ReportController
{
    public function show(Exercise $exercise, AarBuilder $builder): View
    {
        $reports = $exercise->reports()->with('approver')->get();
        $report = $reports->first() ?? (new Report)->forceFill(['exercise_id' => $exercise->id, 'version' => 1, 'tlp' => 'TLP:AMBER', 'content' => []]);

        return view('exercises.report', [
            'exercise' => $exercise,
            'report' => $report,
            'reports' => $reports,
            'aar' => $report->isFinal() ? $report->content : $builder->build($exercise, $report),
        ]);
    }

    public function save(Request $request, Exercise $exercise): RedirectResponse
    {
        $data = $request->validate([
            'tlp' => ['required', Rule::in(Report::TLP)],
            'summary' => ['nullable', 'string', 'max:10000'],
            'phases' => ['array'],
            'phases.*' => ['nullable', 'string', 'max:10000'],
        ]);
        $report = $this->draft($exercise);

        $report->forceFill([
            'tlp' => $data['tlp'],
            'content' => ['summary' => $data['summary'] ?? '', 'phases' => array_map(fn ($text) => (string) $text, $data['phases'] ?? [])],
        ])->save();

        return redirect()->route('exercises.report', $exercise)->with('status', 'Draft saved.');
    }

    /**
     * Make the draft final: freeze the full report and write the evidence files (spec section 4.5, step 3).
     */
    public function finalize(Request $request, Exercise $exercise, AarBuilder $builder, EvidenceExport $evidence): RedirectResponse
    {
        if ($exercise->status !== 'ended') {
            return back()->withErrors(['report' => 'End the session before you make the report final.']);
        }
        $report = $this->draft($exercise);
        $aar = $builder->build($exercise, $report, $request->user());

        $report->forceFill(['status' => 'final', 'content' => $aar, 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        $report->save();
        $report->forceFill(['file_hash' => $evidence->store($report, $aar)])->save();

        return redirect()->route('exercises.report', $exercise)->with('status', "Version {$report->version} is final. Download the evidence package.");
    }

    /**
     * Start the next version from the text of the final version (backlog B6).
     */
    public function newVersion(Exercise $exercise): RedirectResponse
    {
        $latest = $exercise->reports()->firstOrFail();
        abort_unless($latest->isFinal(), 404);

        $next = new Report;
        $next->forceFill([
            'exercise_id' => $exercise->id,
            'version' => $latest->version + 1,
            'tlp' => $latest->tlp,
            'content' => [
                'summary' => $latest->content['summary'] ?? '',
                'phases' => collect($latest->content['phases'] ?? [])->mapWithKeys(fn ($phase) => [Str::lower($phase['phase']) => $phase['text']])->all(),
            ],
        ])->save();

        return redirect()->route('exercises.report', $exercise)->with('status', "Draft version {$next->version} started.");
    }

    public function pdf(Exercise $exercise, Report $report): StreamedResponse
    {
        abort_unless($report->isFinal(), 404);

        return Storage::download($report->folder().'/aar.pdf', Str::slug($exercise->title).'-aar-v'.$report->version.'.pdf');
    }

    public function export(Exercise $exercise, Report $report, EvidenceExport $evidence): BinaryFileResponse
    {
        abort_unless($report->isFinal(), 404);

        return response()->download($evidence->zip($report), Str::slug($exercise->title).'-evidence-v'.$report->version.'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /**
     * The current draft, or a new draft when there is no report yet. A final report is never a draft.
     */
    private function draft(Exercise $exercise): Report
    {
        $latest = $exercise->reports()->first();
        abort_if($latest?->isFinal(), 409, 'This version is final. Start a new version.');
        if ($latest) {
            return $latest;
        }

        $report = new Report;
        $report->forceFill(['exercise_id' => $exercise->id, 'version' => 1, 'tlp' => 'TLP:AMBER', 'content' => []]);

        return $report;
    }
}
