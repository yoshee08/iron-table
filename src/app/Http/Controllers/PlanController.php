<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Models\IrPlan;
use App\Models\PlanSection;
use App\Services\PlanParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The organization's IR plan (spec section 5). Every logged-in user can read
 * and search it. Moderators and administrators upload new versions.
 */
class PlanController
{
    public function index(): View
    {
        return view('plans.index', [
            'plans' => IrPlan::query()->with('uploader')->withCount('sections')->orderByDesc('version')->get(),
        ]);
    }

    public function create(): View
    {
        return view('plans.create');
    }

    public function store(Request $request, PlanParser $parser): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:20480', 'extensions:pdf,docx,md'],
        ]);

        $file = $request->file('file');

        try {
            $sections = $parser->parse($file->getRealPath(), $file->getClientOriginalExtension());
        } catch (Throwable) {
            $sections = [];
        }

        if ($sections === []) {
            return back()->withErrors(['file' => 'IRon Table cannot read this file. Use a PDF with selectable text, a DOCX file or a Markdown file.']);
        }

        $path = $file->store('plans');

        // A new version keeps the organization profile and the plan concepts (by section number) of the version before it.
        $previous = IrPlan::current();
        $concepts = $previous?->sections->whereNotNull('number')->whereNotNull('concept')->pluck('concept', 'number')->all() ?? [];

        $plan = DB::transaction(function () use ($request, $data, $file, $path, $sections, $previous, $concepts) {
            $plan = IrPlan::query()->forceCreate([
                'version' => ((int) IrPlan::query()->max('version')) + 1,
                'title' => ($data['title'] ?? '') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'uploaded_by' => $request->user()->id,
                'profile' => $previous?->profile,
            ]);

            foreach ($sections as $position => $section) {
                $plan->sections()->forceCreate($section + ['position' => $position, 'concept' => $concepts[$section['number'] ?? ''] ?? null]);
            }

            return $plan;
        });

        return redirect()->route('plans.show', $plan)
            ->with('status', 'Version '.$plan->version.' imported with '.count($sections).' sections. Check the sections and correct them if necessary.');
    }

    public function show(Request $request, IrPlan $plan): View
    {
        $terms = trim((string) $request->query('q', ''));

        return view('plans.show', [
            'plan' => $plan,
            'terms' => $terms,
            'sections' => $terms === ''
                ? $plan->sections
                : PlanSection::query()->where('ir_plan_id', $plan->id)->matching($terms)->get(),
            'isCurrent' => IrPlan::current()?->is($plan) ?? false,
        ]);
    }

    public function download(IrPlan $plan): StreamedResponse
    {
        return Storage::download($plan->file_path, $plan->file_name);
    }
}
