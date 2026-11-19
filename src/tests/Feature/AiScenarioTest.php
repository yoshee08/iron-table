<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Models\Scenario;
use App\Models\User;
use App\Services\Ai\Provider;
use App\Services\ScenarioChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\FakeAi;
use Tests\TestCase;

class AiScenarioTest extends TestCase
{
    use RefreshDatabase;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        config(['iron-table.ai.enabled' => true]);
        $this->moderator = User::factory()->moderator()->withTwoFactor()->create();
    }

    /**
     * @param  list<array<string, mixed>|string>  $answers
     */
    private function ai(array $answers): FakeAi
    {
        $ai = new FakeAi($answers);
        $this->app->instance(Provider::class, $ai);

        return $ai;
    }

    private function draft(): Scenario
    {
        $this->ai([ScenarioDrafterTest::OUTLINE, ScenarioDrafterTest::FACTS]);
        $this->actingAs($this->moderator)->post('/scenarios/draft', ['incident' => 'ransomware', 'difficulty' => 'beginner', 'injects' => 3]);

        return Scenario::query()->latest('id')->firstOrFail();
    }

    public function test_ai_is_off_by_default(): void
    {
        config(['iron-table.ai.enabled' => false]);

        $this->actingAs($this->moderator)->get('/scenarios/draft')->assertForbidden();
        $this->actingAs($this->moderator)->get('/scenarios')->assertOk()->assertDontSee('Draft with AI');
    }

    public function test_a_moderator_drafts_a_scenario_and_writes_an_inject(): void
    {
        $this->ai([]);
        $this->actingAs($this->moderator)->get('/scenarios/draft')->assertOk()->assertSee('Test AI. The scenario text stays in the test.');

        $scenario = $this->draft();
        $this->assertSame(['ransomware-on-the-file-server', '0.1.0'], [$scenario->slug, $scenario->version]);
        $this->assertStringStartsWith('# Draft written with AI assistance.', $scenario->source);

        $this->ai([['body' => 'Files on FS-01 cannot be opened.', 'questions' => ['Who do you tell?'], 'expected_plan' => ['reporting_procedure']]]);
        $this->actingAs($this->moderator)->post("/scenarios/{$scenario->id}/ai/inj-1/inject")
            ->assertRedirect("/scenarios/{$scenario->id}#inj-1");

        $data = (new ScenarioChecker)->read($scenario->fresh()->source);
        $this->assertSame('Files on {{facts.file_server}} cannot be opened.', $data['injects'][0]['body']);
        $this->actingAs($this->moderator)->get("/scenarios/{$scenario->id}")->assertOk()
            ->assertSee('Files on FS-01 cannot be opened.')
            ->assertSee('Write the rubric with AI');
    }

    public function test_the_same_title_gets_a_new_id(): void
    {
        $this->draft();
        $this->assertSame('ransomware-on-the-file-server-2', $this->draft()->slug);
    }

    public function test_an_ai_error_changes_nothing(): void
    {
        $scenario = $this->draft();
        $this->ai(['IRon Table cannot reach the local AI.']);

        $this->actingAs($this->moderator)->post("/scenarios/{$scenario->id}/ai/inj-2/artifact")
            ->assertSessionHas('ai_error', 'IRon Table cannot reach the local AI.');
        $this->assertSame($scenario->source, $scenario->fresh()->source);

        $this->ai(['The AI is down.']);
        $this->actingAs($this->moderator)->post('/scenarios/draft', ['incident' => 'phishing', 'difficulty' => 'beginner', 'injects' => 3])
            ->assertSessionHasErrors(['incident' => 'The AI is down.']);
    }

    public function test_only_moderators_use_ai_and_only_on_real_injects(): void
    {
        $scenario = $this->draft();

        $this->actingAs($this->moderator)->post("/scenarios/{$scenario->id}/ai/inj-9/inject")->assertNotFound();
        $this->actingAs($this->moderator)->post("/scenarios/{$scenario->id}/ai/inj-1/delete")->assertNotFound();
        $this->actingAs(User::factory()->create())->post("/scenarios/{$scenario->id}/ai/inj-1/inject")->assertForbidden();
    }
}
