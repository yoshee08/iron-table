<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Models\IrPlan;
use App\Models\Scenario;
use App\Models\ScenarioImage;
use App\Services\Ai\AiException;
use App\Services\Ai\Provider;
use App\Services\Ai\ScenarioDrafter;
use App\Services\ScenarioChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * AI drafting of scenarios, one small step at a time (spec section 9.2).
 * Each step changes the YAML of the scenario. The moderator checks the result
 * on the review screen. AI_ENABLED=false turns all of this off.
 */
class AiScenarioController
{
    private const STEPS = ['inject', 'artifact', 'picture', 'rubric'];

    public function create(Provider $ai): View
    {
        return view('scenarios.draft', ['notice' => $ai->notice()]);
    }

    public function store(Request $request, ScenarioDrafter $drafter): RedirectResponse
    {
        $data = $request->validate([
            'incident' => ['required', 'string', 'max:200'],
            'difficulty' => ['required', 'in:beginner,intermediate,advanced'],
            'injects' => ['required', 'integer', 'min:2', 'max:6'],
        ]);
        set_time_limit(0);

        try {
            $scenario = $drafter->start($data['incident'], $data['difficulty'], (int) $data['injects']);
        } catch (AiException $e) {
            return back()->withInput()->withErrors(['incident' => $e->getMessage()]);
        }

        // The title decides the ID. Add a number if a scenario with this ID and version exists.
        $slug = $scenario['meta']['id'];
        for ($n = 2; Scenario::query()->where('slug', $scenario['meta']['id'])->where('version', '0.1.0')->exists(); $n++) {
            $scenario['meta']['id'] = $slug.'-'.$n;
        }

        $model = new Scenario;
        $model->forceFill([
            'slug' => $scenario['meta']['id'],
            'version' => '0.1.0',
            'title' => $scenario['meta']['title'],
            'source' => self::yaml($scenario),
            'uploaded_by' => $request->user()->id,
        ])->save();

        return redirect()->route('scenarios.show', $model)
            ->with('status', 'The AI wrote the outline and the facts. Check them. Then write each inject with the AI buttons or by hand.');
    }

    public function step(Request $request, Scenario $scenario, string $inject, string $step, ScenarioChecker $checker, ScenarioDrafter $drafter): RedirectResponse
    {
        $data = $checker->read($scenario->source);
        $n = array_search($inject, array_column($data['injects'], 'id'), true);
        abort_if($n === false || ! in_array($step, self::STEPS, true), 404);
        set_time_limit(0);

        $plan = IrPlan::current();
        try {
            $data = match ($step) {
                'inject' => $drafter->inject($data, $n, $plan?->concepts() ?? [], array_keys($plan?->profileValues() ?? [])),
                'artifact' => $drafter->artifact($data, $n),
                'picture' => $drafter->picture($data, $n),
                'rubric' => $drafter->rubric($data, $n),
            };
        } catch (AiException $e) {
            return redirect()->to(route('scenarios.show', $scenario).'#'.$inject)->with('ai_error', $e->getMessage());
        }

        $scenario->forceFill(['source' => self::yaml($data)])->save();

        // A new picture prompt: the image provider makes the picture in the queue worker.
        if ($step === 'picture' && Gate::allows('use-images')) {
            $artifacts = $data['injects'][$n]['artifacts'];
            ScenarioImage::request($scenario, $inject, count($artifacts) - 1, (string) end($artifacts)['prompt'], $request->user()->id);
        }

        return redirect()->to(route('scenarios.show', $scenario).'#'.$inject)
            ->with('status', "The AI changed inject $inject. Check the result.");
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    private static function yaml(array $scenario): string
    {
        return "# Draft written with AI assistance. Check every part.\n".ScenarioChecker::dump($scenario);
    }
}
