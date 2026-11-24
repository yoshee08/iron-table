<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Jobs\GenerateScenarioImage;
use App\Models\IrPlan;
use App\Models\Scenario;
use App\Models\ScenarioImage;
use App\Services\ScenarioChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use finfo;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

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

    /** The largest picture in an imported ZIP file (bytes). */
    private const MAX_IMAGE = 10 * 1024 * 1024;

    public function store(Request $request, ScenarioChecker $checker): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:51200', 'extensions:yaml,yml,zip']]);
        $file = $request->file('file');

        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            return $this->save($request, new Scenario, (string) file_get_contents($file->getRealPath()), 'file', $checker);
        }

        // A scenario with pictures: scenario.yaml and the pictures in images/.
        $zip = new ZipArchive;
        $source = $zip->open($file->getRealPath()) === true ? $zip->getFromName('scenario.yaml') : false;
        if ($source === false) {
            return back()->withErrors(['file' => 'The ZIP file must contain scenario.yaml.']);
        }
        try {
            $names = ScenarioChecker::images($checker->read($source));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $images = [];
        foreach ($names as $name) {
            $size = $zip->statName('images/'.$name)['size'] ?? null;
            $picture = $size !== null && $size <= self::MAX_IMAGE ? $zip->getFromName('images/'.$name) : false;
            if ($picture === false || ! in_array((new finfo(FILEINFO_MIME_TYPE))->buffer($picture), ['image/png', 'image/jpeg', 'image/webp'], true)) {
                return back()->withErrors(['file' => "The ZIP file has no valid picture images/$name (PNG, JPEG or WebP, 10 MB maximum)."]);
            }
            $images[$name] = $picture;
        }

        return $this->save($request, new Scenario, $source, 'file', $checker, $images);
    }

    /**
     * The review screen (spec section 4.1): the result of the consistency check
     * and the scenario with the values of the current IR plan.
     */
    public function show(Scenario $scenario, ScenarioChecker $checker): View
    {
        $plan = IrPlan::current();
        $result = $checker->check($checker->read($scenario->source), $plan?->profileValues() ?? [], $plan?->concepts());
        $pictures = ScenarioImage::query()->where('scenario_id', $scenario->id)->orderByDesc('id')->get();

        return view('scenarios.show', [
            'scenario' => $scenario,
            'plan' => $plan,
            'sections' => $plan?->sections->whereNotNull('concept')->groupBy('concept') ?? collect(),
            'problems' => $result['errors'],
            'facts' => $result['facts'],
            'filled' => $result['scenario'],
            'pictures' => $pictures->groupBy(fn (ScenarioImage $image) => $image->inject_key.'#'.$image->artifact),
            // While a picture is in progress, the page reloads every minute.
            'refresh' => $pictures->contains(fn (ScenarioImage $image) => in_array($image->status, ['queued', 'working'], true)) ? 60 : null,
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

    /**
     * The scenario as YAML, or as a ZIP file with its pictures (scenario.yaml and images/).
     */
    public function download(Scenario $scenario, ScenarioChecker $checker): Response|BinaryFileResponse
    {
        $names = ScenarioChecker::images($checker->read($scenario->source));
        if ($names !== []) {
            $path = (string) tempnam(sys_get_temp_dir(), 'scenario');
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::OVERWRITE);
            $zip->addFromString('scenario.yaml', $scenario->source);
            foreach ($names as $name) {
                $file = GenerateScenarioImage::folder($scenario->id).'/'.$name;
                if (Storage::exists($file)) {
                    $zip->addFromString('images/'.$name, (string) Storage::get($file));
                }
            }
            $zip->close();

            return response()->download($path, $scenario->slug.'-'.$scenario->version.'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
        }

        return response($scenario->source, 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$scenario->slug.'-'.$scenario->version.'.yaml"',
        ]);
    }

    public function destroy(Scenario $scenario): RedirectResponse
    {
        $scenario->delete();
        Storage::deleteDirectory(GenerateScenarioImage::folder($scenario->id));

        return redirect()->route('scenarios.index')->with('status', 'Scenario deleted.');
    }

    /**
     * @param  array<string, string>  $images  pictures from an imported ZIP file: file name => contents
     */
    private function save(Request $request, Scenario $scenario, string $source, string $field, ScenarioChecker $checker, array $images = []): RedirectResponse
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
        foreach ($images as $name => $picture) {
            Storage::put(GenerateScenarioImage::folder($scenario->id).'/'.$name, $picture);
        }

        return redirect()->route('scenarios.show', $scenario)->with('status', 'Scenario saved. Correct all errors that the check shows.');
    }
}
