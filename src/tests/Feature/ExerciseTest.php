<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Events\ExerciseChanged;
use App\Models\Exercise;
use App\Models\IrPlan;
use App\Models\Scenario;
use App\Models\User;
use App\Services\ScenarioChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\FakeImages;
use Tests\TestCase;

class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    private User $moderator;

    private User $player;

    private IrPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moderator = User::factory()->moderator()->withTwoFactor()->create();
        $this->player = User::factory()->create(['name' => 'Pat Player']);

        $this->plan = IrPlan::query()->forceCreate([
            'version' => 1, 'title' => 'Example Corp IR Plan', 'file_name' => 'plan.md', 'file_path' => 'plans/plan.md',
            'profile' => "name: Example Corp\ndomain:\n  example_user: analyst@example.com\nchannel:\n  report: security@example.com\n",
        ]);
        foreach (['Reporting a Suspected Incident' => 'reporting_procedure', 'Severity Table' => 'severity_table', 'Containment' => 'containment', 'External Reporting' => 'external_reporting'] as $title => $concept) {
            $this->plan->sections()->forceCreate(['position' => $this->plan->sections()->count(), 'number' => (string) ($this->plan->sections()->count() + 1), 'title' => $title, 'body' => 'Text.', 'concept' => $concept]);
        }

        $scenario = new Scenario;
        $scenario->forceFill(['slug' => 'phish-to-mailbox-rule', 'version' => '1.0.0', 'title' => 'Phishing leads to a malicious mailbox rule',
            'source' => (string) file_get_contents(resource_path('scenarios/phish-to-mailbox-rule.yaml'))])->save();
    }

    /**
     * A prepared exercise with Pat Player as a participant (plan role CISO).
     */
    private function exercise(): Exercise
    {
        $this->actingAs($this->moderator)->post('/exercises', ['scenario_id' => Scenario::query()->sole()->id]);
        $exercise = Exercise::query()->latest('id')->firstOrFail();
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/participants", ['user_id' => $this->player->id, 'role' => 'participant', 'plan_role' => 'CISO']);

        return $exercise;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function control(Exercise $exercise, string $action, array $data = []): TestResponse
    {
        return $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/$action", $data);
    }

    public function test_a_moderator_prepares_an_exercise_with_a_frozen_copy_of_the_scenario(): void
    {
        $this->actingAs($this->moderator)->post('/exercises', ['scenario_id' => Scenario::query()->sole()->id])
            ->assertRedirect('/exercises/'.Exercise::query()->sole()->id.'/setup');

        $exercise = Exercise::query()->sole();
        $this->assertSame(['prepared', '1.0.0', $this->plan->id], [$exercise->status, $exercise->scenario_version, $exercise->ir_plan_id]);
        $this->assertStringContainsString('Mailbox: analyst@example.com', $exercise->scenario['injects'][0]['artifacts'][0]['content']);
    }

    public function test_a_scenario_with_check_errors_cannot_become_an_exercise(): void
    {
        $this->plan->forceFill(['profile' => null])->save();

        $this->actingAs($this->moderator)->post('/exercises', ['scenario_id' => Scenario::query()->sole()->id])->assertSessionHasErrors('scenario_id');
        $this->assertSame(0, Exercise::query()->count());
    }

    public function test_participants_see_their_exercises_and_others_do_not(): void
    {
        $exercise = $this->exercise();
        $stranger = User::factory()->create();

        $this->actingAs($this->player)->get('/exercises')->assertOk()->assertSee($exercise->title);
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/live")->assertOk()->assertSee('CISO')->assertSee('The session has not started.');
        $this->actingAs($stranger)->get('/exercises')->assertOk()->assertDontSee($exercise->title);
        $this->actingAs($stranger)->get("/exercises/{$exercise->id}/live")->assertForbidden();
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/control")->assertForbidden();
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/participants", ['user_id' => $this->player->id, 'role' => 'observer'])->assertSessionHasErrors('user_id');
    }

    public function test_the_moderator_runs_the_session_and_releases_injects(): void
    {
        Event::fake([ExerciseChanged::class]);
        $exercise = $this->exercise();

        $this->control($exercise, 'release', ['inject' => 'inj-1'])->assertSessionHasErrors('session');
        $this->control($exercise, 'session/start');
        $this->assertSame(['running', '10:40'], [$exercise->fresh()->status, $exercise->fresh()->sim_time]);

        $this->control($exercise, 'release', ['inject' => 'inj-1'])->assertSessionHasNoErrors();
        $this->control($exercise, 'release', ['inject' => 'inj-1'])->assertSessionHasErrors('session');
        $this->control($exercise, 'release', ['inject' => 'inj-9'])->assertNotFound();
        Event::assertDispatched(ExerciseChanged::class, fn (ExerciseChanged $event) => $event->exerciseId === $exercise->id);

        // Out of order: the clock goes forward to 13:00 and does not go back for an earlier inject.
        $this->control($exercise, 'release', ['inject' => 'inj-3']);
        $this->control($exercise, 'release', ['inject' => 'inj-2a']);
        $this->assertSame('13:00', $exercise->fresh()->sim_time);
        $this->assertSame('13:00', $exercise->releases()->where('inject_key', 'inj-2a')->sole()->sim_time);

        $this->control($exercise, 'clock', ['sim_day' => 1, 'sim_time' => '12:00'])->assertSessionHasErrors('sim_time');
        $this->control($exercise, 'clock', ['sim_day' => 2, 'sim_time' => '08:00'])->assertSessionHasNoErrors();
        $this->assertSame('day 2 08:00', $exercise->fresh()->simClock());

        $release = $exercise->releases()->where('inject_key', 'inj-1')->sole();
        $this->assertStringContainsString('The help desk finds a rule on the mailbox of the Finance analyst.', $release->body);
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/live")->assertOk()
            ->assertSee('The help desk reports a strange mailbox rule')
            ->assertSee('Mailbox: analyst@example.com')
            ->assertSee('Open large');

        $this->control($exercise, 'session/end');
        $this->control($exercise, 'session/resume')->assertSessionHasErrors('session');
        $this->assertSame('ended', $exercise->fresh()->status);
    }

    public function test_a_participant_responds_and_cites_the_plan(): void
    {
        $exercise = $this->exercise();
        $this->control($exercise, 'session/start');
        $this->control($exercise, 'release', ['inject' => 'inj-1']);
        $release = $exercise->releases()->sole();
        $section = $this->plan->sections()->where('concept', 'reporting_procedure')->sole();

        $this->actingAs($this->player)->post("/exercises/{$exercise->id}/responses", [
            'inject_release_id' => $release->id, 'body' => 'We report it to security@example.com now.', 'sections' => [$section->id], 'no_coverage' => '0',
        ])->assertRedirect("/exercises/{$exercise->id}/live#release-{$release->id}");

        $response = $exercise->responses()->sole();
        $this->assertSame(['CISO', '10:40', false], [$response->plan_role, $response->sim_time, $response->no_coverage]);
        $this->assertSame([$section->id], $response->citations->pluck('id')->all());

        $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/control")->assertOk()
            ->assertSee('We report it to security@example.com now.')
            ->assertSee('Pat Player (CISO)')
            ->assertSee('Cites: 1 Reporting a Suspected Incident');

        // A section of another plan cannot be cited.
        $other = IrPlan::query()->forceCreate(['version' => 2, 'title' => 'New', 'file_name' => 'n.md', 'file_path' => 'plans/n.md']);
        $foreign = $other->sections()->forceCreate(['position' => 0, 'title' => 'Other', 'body' => '']);
        $this->actingAs($this->player)->post("/exercises/{$exercise->id}/responses", ['inject_release_id' => $release->id, 'body' => 'X', 'sections' => [$foreign->id]])
            ->assertSessionHasErrors('sections.0');
    }

    public function test_the_exercise_keeps_its_own_copy_of_the_pictures(): void
    {
        Storage::fake('local');
        $scenario = Scenario::query()->sole();
        $this->actingAs($this->moderator)->post("/scenarios/{$scenario->id}/pictures/inj-1", [
            'title' => 'Server room door', 'file' => UploadedFile::fake()->createWithContent('door.png', FakeImages::png()),
        ]);
        $artifacts = (new ScenarioChecker)->read($scenario->fresh()->source)['injects'][0]['artifacts'];
        $name = end($artifacts)['image'];

        $exercise = $this->exercise();
        Storage::assertExists("exercise-images/{$exercise->id}/$name");
        $this->control($exercise, 'session/start');
        $this->control($exercise, 'release', ['inject' => 'inj-1']);

        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/live")->assertOk()->assertSee("/exercises/{$exercise->id}/images/$name");
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/images/$name")->assertOk();
        $this->actingAs(User::factory()->create())->get("/exercises/{$exercise->id}/images/$name")->assertForbidden();
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/images/missing.png")->assertNotFound();

        // A missing picture stops a new exercise.
        Storage::delete("scenario-images/{$scenario->id}/$name");
        $this->actingAs($this->moderator)->post('/exercises', ['scenario_id' => $scenario->id])->assertSessionHasErrors('scenario_id');
    }

    public function test_the_scribe_writes_the_logs_and_the_moderator_sees_them(): void
    {
        $exercise = $this->exercise();
        $scribe = User::factory()->create(['name' => 'Sam Scribe']);
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/participants", ['user_id' => $scribe->id, 'role' => 'scribe']);
        $entry = ['direction' => 'internal', 'from' => 'CISO', 'to' => 'Help desk', 'summary' => 'Disable the account now.'];

        $this->actingAs($scribe)->post("/exercises/{$exercise->id}/logs/communication", $entry)->assertForbidden();
        $this->control($exercise, 'session/start');

        $this->actingAs($scribe)->post("/exercises/{$exercise->id}/logs/communication", $entry)->assertSessionHasNoErrors();
        $log = $exercise->logEntries()->sole();
        $this->assertSame(['communication', '10:40', $scribe->id, $entry], [$log->type, $log->sim_time, $log->recorded_by, $log->fields]);

        $this->actingAs($scribe)->post("/exercises/{$exercise->id}/logs/action", ['phase' => 'lunch'])->assertSessionHasErrors('phase');
        $this->actingAs($scribe)->post("/exercises/{$exercise->id}/logs/validation", ['system' => ''])->assertSessionHasErrors('log');
        $this->actingAs($scribe)->post("/exercises/{$exercise->id}/logs/note", ['note' => 'X'])->assertForbidden();
        $this->actingAs($this->player)->post("/exercises/{$exercise->id}/logs/parking", ['topic' => 'X'])->assertForbidden();
        $this->actingAs($scribe)->post("/exercises/{$exercise->id}/logs/lunch", ['topic' => 'X'])->assertNotFound();

        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/logs/note", ['note' => 'Ask about backups at the debrief.']);
        $this->actingAs($scribe)->post("/exercises/{$exercise->id}/logs/parking", ['topic' => 'Who owns the MSP contract?']);

        $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/control")->assertOk()
            ->assertSee('Disable the account now.')->assertSee('Sam Scribe')
            ->assertSee('Ask about backups at the debrief.')->assertSee('Who owns the MSP contract?');
        $this->actingAs($scribe)->get("/exercises/{$exercise->id}/live")->assertOk()
            ->assertSee('Scribe: logs')->assertSee('Disable the account now.')->assertDontSee('Ask about backups at the debrief.');
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/live")->assertOk()
            ->assertDontSee('Scribe: logs')->assertDontSee('Disable the account now.');
    }

    public function test_the_moderator_writes_a_new_inject_during_play(): void
    {
        $exercise = $this->exercise();
        $inject = ['title' => 'The CEO calls', 'body' => 'The CEO asks if the press knows about this.'];

        $this->control($exercise, 'write', $inject)->assertSessionHasErrors('session');
        $this->control($exercise, 'session/start');
        $this->control($exercise, 'write', $inject)->assertSessionHasNoErrors();
        $this->control($exercise, 'write', ['title' => 'Second', 'body' => 'Text.']);

        $this->assertSame(['new-2', 'new-1'], $exercise->releases()->pluck('inject_key')->all());
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/live")->assertOk()->assertSee('The CEO asks if the press knows about this.');
    }

    public function test_the_console_shows_the_plan_coverage_of_the_current_inject(): void
    {
        $exercise = $this->exercise();
        $other = User::factory()->create();
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/participants", ['user_id' => $other->id, 'role' => 'participant']);
        $this->control($exercise, 'session/start');
        $this->control($exercise, 'release', ['inject' => 'inj-1']);
        $release = $exercise->releases()->sole();
        $reporting = $this->plan->sections()->where('concept', 'reporting_procedure')->sole();

        $this->actingAs($this->player)->post("/exercises/{$exercise->id}/responses", ['inject_release_id' => $release->id, 'body' => 'We report it.', 'sections' => [$reporting->id]]);
        $this->actingAs($other)->post("/exercises/{$exercise->id}/responses", ['inject_release_id' => $release->id, 'body' => 'We call the bank.', 'no_coverage' => '1']);

        $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/control")->assertOk()
            ->assertSeeText('Used: 1 (reporting_procedure)')
            ->assertSeeText('Missed: 1 (severity_table)')
            ->assertSeeText('No plan coverage: 1 of 2 responses');
    }

    public function test_only_participants_respond_and_only_while_the_session_runs(): void
    {
        $exercise = $this->exercise();
        $scribe = User::factory()->create();
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/participants", ['user_id' => $scribe->id, 'role' => 'scribe']);
        $this->control($exercise, 'session/start');
        $this->control($exercise, 'release', ['inject' => 'inj-1']);
        $data = ['inject_release_id' => $exercise->releases()->sole()->id, 'body' => 'An answer.'];

        $this->actingAs($scribe)->post("/exercises/{$exercise->id}/responses", $data)->assertForbidden();
        $this->actingAs($scribe)->get("/exercises/{$exercise->id}/live")->assertOk()->assertDontSee('Your response (decision, action or answer)');

        $this->control($exercise, 'session/stop');
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/live")->assertOk()->assertSee('EXERCISE STOPPED');
        $this->actingAs($this->player)->post("/exercises/{$exercise->id}/responses", $data)->assertForbidden();
        $this->assertSame(0, $exercise->responses()->count());
    }
}
