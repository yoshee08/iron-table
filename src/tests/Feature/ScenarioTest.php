<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Models\IrPlan;
use App\Models\Scenario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ScenarioTest extends TestCase
{
    use RefreshDatabase;

    private const PROFILE = <<<'YAML'
        name: Example Corp
        domain:
          example_user: analyst@example.com
        channel:
          report: security@example.com
        YAML;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->moderator = User::factory()->moderator()->withTwoFactor()->create();
    }

    private function example(): string
    {
        return (string) file_get_contents(resource_path('scenarios/phish-to-mailbox-rule.yaml'));
    }

    private function import(string $source, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->moderator)->post('/scenarios', [
            'file' => UploadedFile::fake()->createWithContent('scenario.yaml', $source),
        ]);
    }

    /**
     * An IR plan with a profile and one section for each plan concept of the example.
     */
    private function plan(): IrPlan
    {
        $plan = IrPlan::query()->forceCreate([
            'version' => 1, 'title' => 'Example Corp IR Plan', 'file_name' => 'plan.md', 'file_path' => 'plans/plan.md',
            'profile' => self::PROFILE,
        ]);
        foreach (['reporting_procedure', 'severity_table', 'containment', 'external_reporting'] as $n => $concept) {
            $plan->sections()->forceCreate([
                'position' => $n, 'number' => (string) ($n + 1), 'title' => ucfirst(str_replace('_', ' ', $concept)),
                'body' => 'Text.', 'concept' => $concept,
            ]);
        }

        return $plan;
    }

    public function test_a_moderator_imports_a_scenario_and_sees_the_check(): void
    {
        $response = $this->import($this->example());

        $scenario = Scenario::query()->sole();
        $response->assertRedirect("/scenarios/{$scenario->id}");
        $this->assertSame(['phish-to-mailbox-rule', '1.0.0'], [$scenario->slug, $scenario->version]);
        $this->assertSame($this->example(), $scenario->source);

        $this->actingAs($this->moderator)->get("/scenarios/{$scenario->id}")
            ->assertOk()
            ->assertSee('There is no IR plan. Upload the plan, then set the plan concepts on its sections.')
            ->assertSee('{{org.name}} has no value', false)
            ->assertSee('The help desk reports a strange mailbox rule');
    }

    public function test_with_a_plan_and_profile_the_example_has_no_errors(): void
    {
        $this->plan();
        $this->import($this->example());

        $this->actingAs($this->moderator)->get('/scenarios/'.Scenario::query()->sole()->id)
            ->assertOk()
            ->assertSee('The check found no errors.')
            ->assertSee('Mailbox: analyst@example.com')
            ->assertSee('Report sent through security@example.com')
            ->assertSee('Severity table');
    }

    public function test_a_user_cannot_see_or_import_scenarios(): void
    {
        $this->import($this->example());
        $scenario = Scenario::query()->sole();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/scenarios')->assertForbidden();
        $this->actingAs($user)->get("/scenarios/{$scenario->id}")->assertForbidden();
        $this->actingAs($user)->get("/scenarios/{$scenario->id}/download")->assertForbidden();
        $this->import($this->example(), $user)->assertForbidden();
    }

    public function test_a_wrong_file_or_an_existing_version_is_refused(): void
    {
        $this->import('meta: [')->assertSessionHasErrors('file');
        $this->import($this->example());
        $this->import($this->example())->assertSessionHasErrors(['file' => 'Version 1.0.0 of the scenario phish-to-mailbox-rule exists. Change meta.version, or edit that scenario.']);

        $this->import(str_replace('version: 1.0.0', 'version: 1.1.0', $this->example()))->assertSessionHasNoErrors();
        $this->assertSame(['1.0.0', '1.1.0'], Scenario::query()->orderBy('version')->pluck('version')->all());
    }

    public function test_a_moderator_edits_downloads_and_deletes_a_scenario(): void
    {
        $this->import($this->example());
        $scenario = Scenario::query()->sole();
        $changed = str_replace('title: Phishing leads to a malicious mailbox rule', 'title: Phishing and a mailbox rule', $this->example());

        $this->actingAs($this->moderator)->get("/scenarios/{$scenario->id}/edit")->assertOk();
        $this->actingAs($this->moderator)->put("/scenarios/{$scenario->id}", ['source' => 'meta: ['])->assertSessionHasErrors('source');
        $this->actingAs($this->moderator)->put("/scenarios/{$scenario->id}", ['source' => $changed])->assertRedirect("/scenarios/{$scenario->id}");
        $this->assertSame('Phishing and a mailbox rule', $scenario->fresh()->title);

        $download = $this->actingAs($this->moderator)->get("/scenarios/{$scenario->id}/download");
        $download->assertOk()->assertDownload('phish-to-mailbox-rule-1.0.0.yaml');
        $this->assertSame($changed, $download->getContent());

        $this->actingAs($this->moderator)->get('/scenarios/example')->assertOk()->assertDownload('phish-to-mailbox-rule.yaml');

        $this->actingAs($this->moderator)->delete("/scenarios/{$scenario->id}")->assertRedirect('/scenarios');
        $this->assertModelMissing($scenario);
    }

    public function test_a_moderator_types_the_profile_and_sets_plan_concepts(): void
    {
        $plan = $this->plan();
        $section = $plan->sections->first();

        $this->actingAs($this->moderator)->get("/plans/{$plan->id}/profile")->assertOk()->assertSee('Example Corp');
        $this->actingAs($this->moderator)->put("/plans/{$plan->id}/profile", ['profile' => "name: [\n"])->assertSessionHasErrors('profile');
        $this->actingAs($this->moderator)->put("/plans/{$plan->id}/profile", ['profile' => 'only text'])->assertSessionHasErrors('profile');
        $this->actingAs($this->moderator)->put("/plans/{$plan->id}/profile", ['profile' => "name: New Corp\nrole:\n  ciso:\n    name: Pat Example\n"])
            ->assertRedirect("/plans/{$plan->id}");
        $this->assertSame(['org.name' => 'New Corp', 'org.role.ciso.name' => 'Pat Example'], $plan->fresh()->profileValues());

        $this->actingAs($this->moderator)
            ->put("/plans/{$plan->id}/sections/{$section->id}", ['title' => $section->title, 'concept' => 'Not Valid'])
            ->assertSessionHasErrors('concept');
        $this->actingAs($this->moderator)
            ->put("/plans/{$plan->id}/sections/{$section->id}", ['number' => '1', 'title' => $section->title, 'concept' => 'escalation'])
            ->assertSessionHasNoErrors();
        $this->assertSame('escalation', $section->fresh()->concept);

        $user = User::factory()->create();
        $this->actingAs($user)->get("/plans/{$plan->id}/profile")->assertForbidden();
    }

    public function test_a_new_plan_version_keeps_the_profile_and_the_plan_concepts(): void
    {
        $this->actingAs($this->moderator)->post('/plans', [
            'file' => new UploadedFile(base_path('tests/Fixtures/plans/example-plan.md'), 'example-plan.md', null, null, true),
        ]);
        $first = IrPlan::query()->sole();
        $first->forceFill(['profile' => self::PROFILE])->save();
        $first->sections->firstWhere('number', '4.1')->update(['concept' => 'severity_table']);

        $this->actingAs($this->moderator)->post('/plans', [
            'file' => new UploadedFile(base_path('tests/Fixtures/plans/example-plan.docx'), 'example-plan.docx', null, null, true),
        ]);
        $second = IrPlan::current();

        $this->assertSame(2, $second->version);
        $this->assertSame(self::PROFILE, $second->profile);
        $this->assertSame(['severity_table'], $second->concepts());
        $this->assertSame('4.1', $second->sections->firstWhere('concept', 'severity_table')->number);
    }
}
