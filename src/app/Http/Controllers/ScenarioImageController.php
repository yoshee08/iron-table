<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Jobs\GenerateScenarioImage;
use App\Models\Scenario;
use App\Models\ScenarioImage;
use App\Services\ScenarioChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Picture artifacts (spec section 14, image artifacts). Each picture has an AI
 * prompt by default. The moderator generates a picture with the image provider
 * and chooses it, or uploads a picture.
 */
class ScenarioImageController
{
    private const UPLOAD = ['file', 'max:5120', 'mimes:png,jpg,jpeg,webp'];

    public function add(Request $request, Scenario $scenario, string $inject, ScenarioChecker $checker): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'prompt' => ['nullable', 'string', 'max:2000', 'required_without:file'],
            'file' => ['nullable', ...self::UPLOAD],
        ]);
        [$source, $n] = $this->find($scenario, $inject, $checker);

        $artifact = ['type' => 'image', 'title' => $data['title']];
        if (filled($data['prompt'] ?? null)) {
            $artifact['prompt'] = $data['prompt'];
        }
        if ($request->hasFile('file')) {
            $artifact['image'] = $this->store($scenario, $request->file('file'));
        }
        $source['injects'][$n]['artifacts'][] = $artifact;
        $this->save($scenario, $source);

        $status = 'Picture added.';
        if (! isset($artifact['image']) && Gate::allows('use-images')) {
            ScenarioImage::request($scenario, $inject, count($source['injects'][$n]['artifacts']) - 1, $artifact['prompt'], $request->user()->id);
            $status = 'Picture added. The image service makes it now. This page shows it when it is ready.';
        }

        return $this->back($scenario, $inject, $status);
    }

    public function generate(Request $request, Scenario $scenario, string $inject, int $artifact, ScenarioChecker $checker): RedirectResponse
    {
        [$source, $n] = $this->find($scenario, $inject, $checker);
        $item = $source['injects'][$n]['artifacts'][$artifact] ?? null;
        abort_unless(($item['type'] ?? null) === 'image' && filled($item['prompt'] ?? null), 404);

        ScenarioImage::request($scenario, $inject, $artifact, (string) $item['prompt'], $request->user()->id);

        return $this->back($scenario, $inject, 'The image service makes the picture now. This page shows it when it is ready.');
    }

    /**
     * Use a finished AI picture for its artifact.
     */
    public function choose(Scenario $scenario, ScenarioImage $image, ScenarioChecker $checker): RedirectResponse
    {
        abort_unless($image->scenario_id === $scenario->id && $image->status === 'done', 404);
        [$source, $n] = $this->find($scenario, $image->inject_key, $checker);
        abort_unless(($source['injects'][$n]['artifacts'][$image->artifact]['type'] ?? null) === 'image', 404);

        $source['injects'][$n]['artifacts'][$image->artifact]['image'] = $image->file;
        $this->save($scenario, $source);

        return $this->back($scenario, $image->inject_key, 'Picture chosen.');
    }

    public function upload(Request $request, Scenario $scenario, string $inject, int $artifact, ScenarioChecker $checker): RedirectResponse
    {
        $request->validate(['file' => ['required', ...self::UPLOAD]]);
        [$source, $n] = $this->find($scenario, $inject, $checker);
        abort_unless(($source['injects'][$n]['artifacts'][$artifact]['type'] ?? null) === 'image', 404);

        $source['injects'][$n]['artifacts'][$artifact]['image'] = $this->store($scenario, $request->file('file'));
        $this->save($scenario, $source);

        return $this->back($scenario, $inject, 'Picture uploaded.');
    }

    public function file(Scenario $scenario, string $name): StreamedResponse
    {
        $path = GenerateScenarioImage::folder($scenario->id).'/'.$name;
        abort_unless(preg_match(ScenarioChecker::IMAGE_NAME, $name) && Storage::exists($path), 404);

        return Storage::response($path);
    }

    /**
     * @return array{0: array<string, mixed>, 1: int} the scenario data and the index of the inject
     */
    private function find(Scenario $scenario, string $inject, ScenarioChecker $checker): array
    {
        $source = $checker->read($scenario->source);
        $n = array_search($inject, array_column($source['injects'], 'id'), true);
        abort_if($n === false, 404);

        return [$source, $n];
    }

    private function store(Scenario $scenario, UploadedFile $file): string
    {
        $name = Str::lower(Str::random(24)).'.'.$file->extension();
        $file->storeAs(GenerateScenarioImage::folder($scenario->id), $name);

        return $name;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function save(Scenario $scenario, array $source): void
    {
        $scenario->forceFill(['source' => ScenarioChecker::dump($source)])->save();
    }

    private function back(Scenario $scenario, string $inject, string $status): RedirectResponse
    {
        return redirect()->to(route('scenarios.show', $scenario).'#'.$inject)->with('status', $status);
    }
}
