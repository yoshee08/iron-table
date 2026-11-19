<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Models\IrPlan;
use App\Models\Scenario;
use App\Services\ScenarioChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The scenario library (spec section 6). Moderators and administrators only:
 * participants must not see the injects before the session.
 */
class ScenarioController
{
    private const EXAMPLE = 'scenarios/phish-to-mailbox-rule.yaml';

    public function index(): View
    {
        return view('scenarios.index', [
            'scenarios' => Scenario::query()->with('uploader')->orderBy('title')->orderByDesc('created_at')->get(),
        ]);
    }

    public function create(): View
    {
        return view('scenarios.create');
    }

    public function example(): BinaryFileResponse
    {
        return response()->download(resource_path(self::EXAMPLE));
    }

    public function store(Request $request, ScenarioChecker $checker): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:1024', 'extensions:yaml,yml']]);

        return $this->save($request, new Scenario, (string) file_get_contents($request->file('file')->getRealPath()), 'file', $checker);
    }

    /**
     * The review screen (spec section 4.1): the result of the consistency check
     * and the scenario with the values of the current IR plan.
     */
    public function show(Scenario $scenario, ScenarioChecker $checker): View
    {
        $plan = IrPlan::current();
        $result = $checker->check($checker->read($scenario->source), $plan?->profileValues() ?? [], $plan?->concepts());

        return view('scenarios.show', [
            'scenario' => $scenario,
            'plan' => $plan,
            'sections' => $plan?->sections->whereNotNull('concept')->groupBy('concept') ?? collect(),
            'problems' => $result['errors'],
            'facts' => $result['facts'],
            'filled' => $result['scenario'],
        ]);
    }

    public function edit(Scenario $scenario): View
    {
        return view('scenarios.edit', ['scenario' => $scenario]);
    }

    public function update(Request $request, Scenario $scenario, ScenarioChecker $checker): RedirectResponse
    {
        $request->validate(['source' => ['required', 'string', 'max:1048576']]);

        // Browsers send CRLF line ends from a textarea.
        return $this->save($request, $scenario, str_replace("\r\n", "\n", (string) $request->input('source')), 'source', $checker);
    }

    public function download(Scenario $scenario): Response
    {
        return response($scenario->source, 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$scenario->slug.'-'.$scenario->version.'.yaml"',
        ]);
    }

    public function destroy(Scenario $scenario): RedirectResponse
    {
        $scenario->delete();

        return redirect()->route('scenarios.index')->with('status', 'Scenario deleted.');
    }

    private function save(Request $request, Scenario $scenario, string $source, string $field, ScenarioChecker $checker): RedirectResponse
    {
        try {
            $meta = $checker->read($source)['meta'];
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors([$field => $e->getMessage()]);
        }

        $slug = (string) $meta['id'];
        $version = (string) $meta['version'];
        $exists = Scenario::query()->where('slug', $slug)->where('version', $version)
            ->when($scenario->exists, fn ($query) => $query->whereKeyNot($scenario->id))
            ->exists();
        if ($exists) {
            return back()->withInput()->withErrors([$field => "Version $version of the scenario $slug exists. Change meta.version, or edit that scenario."]);
        }

        $scenario->forceFill(['slug' => $slug, 'version' => $version, 'title' => (string) $meta['title'], 'source' => $source]);
        $scenario->uploaded_by ??= $request->user()->id;
        $scenario->save();

        return redirect()->route('scenarios.show', $scenario)->with('status', 'Scenario saved. Correct all errors that the check shows.');
    }
}
