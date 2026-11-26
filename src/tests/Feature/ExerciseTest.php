<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Events\ExerciseChanged;
use App\Http\Controllers\ReviewController;
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
use ZipArchive;

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

    public function test_a_role_only_inject_is_seen_only_by_that_role(): void
    {
        $exercise = $this->exercise();
        $legal = User::factory()->create();
        $scribe = User::factory()->create();
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/participants", ['user_id' => $legal->id, 'role' => 'participant', 'plan_role' => 'Legal counsel']);
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/participants", ['user_id' => $scribe->id, 'role' => 'scribe']);
        $this->control($exercise, 'session/start');
        $this->control($exercise, 'write', ['title' => 'Litigation hold', 'body' => 'Outside counsel asks for a litigation hold.', 'audience' => 'Legal counsel, observer', 'timer_minutes' => 10]);
        $release = $exercise->releases()->sole();
        $this->assertSame([['Legal counsel', 'observer'], 10], [$release->audience, $release->timer_minutes]);

        $this->actingAs($legal)->get("/exercises/{$exercise->id}/live")->assertSee('Outside counsel asks for a litigation hold.')
            ->assertSee('Only for: Legal counsel, observer')->assertSee('Timer (10 min)');
        $this->actingAs($scribe)->get("/exercises/{$exercise->id}/live")->assertSee('Outside counsel asks for a litigation hold.');
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/live")->assertDontSee('Outside counsel asks for a litigation hold.');
        $this->actingAs($this->player)->post("/exercises/{$exercise->id}/responses", ['inject_release_id' => $release->id, 'body' => 'X'])->assertForbidden();
        $this->actingAs($legal)->post("/exercises/{$exercise->id}/responses", ['inject_release_id' => $release->id, 'body' => 'Hold issued.'])->assertRedirect();

        // The shared display shows only injects for everybody.
        $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/display")->assertOk()->assertDontSee('Outside counsel asks for a litigation hold.');
    }

    public function test_participants_confirm_their_plan_role(): void
    {
        $exercise = $this->exercise();
        $this->control($exercise, 'session/start');

        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/live")->assertSee('Confirm my role')->assertSee('You play:');
        $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/control")->assertSeeText('Roles confirmed: 0 of 1 (waiting: Pat Player)');

        $this->actingAs($this->player)->post("/exercises/{$exercise->id}/confirm")->assertRedirect();
        $this->assertNotNull($exercise->participants()->sole()->confirmed_at);
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/live")->assertDontSee('Confirm my role');
        $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/control")->assertSeeText('Roles confirmed: 1 of 1');
        $this->actingAs(User::factory()->create())->post("/exercises/{$exercise->id}/confirm")->assertForbidden();
    }

    public function test_the_shared_display_shows_the_current_inject_only(): void
    {
        $exercise = $this->exercise();
        $this->control($exercise, 'session/start');
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/logs/note", ['note' => 'Secret moderator note.']);

        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/display")->assertOk()->assertSee('It is a normal Tuesday');
        $this->control($exercise, 'release', ['inject' => 'inj-1']);
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/display")->assertOk()
            ->assertSee('The help desk reports a strange mailbox rule')
            ->assertSee('Mailbox: analyst@example.com')
            ->assertSee('10:40')
            ->assertDontSee('Secret moderator note.')
            ->assertDontSee('Severity matches the plan table');
        $this->actingAs(User::factory()->create())->get("/exercises/{$exercise->id}/display")->assertForbidden();
    }

    public function test_the_moderator_scores_the_rubric_by_phase(): void
    {
        $exercise = $this->exercise();
        $url = "/exercises/{$exercise->id}/review/marks";

        $this->actingAs($this->moderator)->post($url, ['marks' => [
            'inj-1' => [['result' => 'met'], ['result' => 'partly']],
            'inj-3' => [['result' => 'not_met']],
        ]])->assertSessionHasNoErrors();
        $scores = $exercise->fresh()->scores();
        $this->assertSame([3, 8, 37], [$scores['earned'], $scores['possible'], $scores['percent']]);
        $this->assertSame(['detection' => ['earned' => 3, 'possible' => 4], 'post-incident' => ['earned' => 0, 'possible' => 4]], $scores['phases']);

        // A changed score needs a reason, and the reason is kept.
        $this->actingAs($this->moderator)->post($url, ['marks' => ['inj-3' => [['result' => 'not_met', 'points' => 1]]]])->assertSessionHasErrors('marks.inj-3.0.reason');
        $this->actingAs($this->moderator)->post($url, ['marks' => ['inj-3' => [['result' => 'not_met', 'points' => 1, 'reason' => 'They named the legal team late.']]]])->assertSessionHasNoErrors();
        $this->assertSame('They named the legal team late.', $exercise->marks()->where('inject_key', 'inj-3')->sole()->reason);

        $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/review")->assertOk()->assertSee('50%')->assertSee('They named the legal team late.');
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/review")->assertForbidden();
    }

    public function test_an_objective_rating_needs_a_justification(): void
    {
        $exercise = $this->exercise();

        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/review/objectives/0", ['rating' => 'some'])->assertSessionHasErrors('justification');
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/review/objectives/0", ['rating' => 'some', 'justification' => 'The report went out after 40 minutes.']);
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/review/objectives/9", ['rating' => 'some', 'justification' => 'X'])->assertNotFound();

        $this->assertSame(['some', 'The report went out after 40 minutes.'], [$exercise->ratings()->sole()->rating, $exercise->ratings()->sole()->justification]);
    }

    public function test_findings_and_corrective_actions(): void
    {
        $exercise = $this->exercise();
        $url = "/exercises/{$exercise->id}/findings";

        $this->actingAs($this->moderator)->post($url, ['type' => 'plan_gap', 'text' => 'The plan names no CUI decision owner.', 'action' => 'Name the owner.'])
            ->assertSessionHasErrors(['owner', 'due_date']);
        $this->actingAs($this->moderator)->post($url, ['type' => 'plan_gap', 'text' => 'The plan names no CUI decision owner.', 'control' => '3.6.1',
            'action' => 'Name the owner in section 5.', 'owner' => 'CISO', 'due_date' => '2020-01-31', 'documents' => 'IR plan section 5'])->assertSessionHasNoErrors();
        $this->actingAs($this->moderator)->post($url, ['type' => 'strength', 'text' => 'Fast report to security.']);

        $finding = $exercise->findings()->first();
        $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/review")->assertOk()
            ->assertSee('The plan names no CUI decision owner.')->assertSee('(overdue)')->assertSee('Fast report to security.');
        $this->actingAs($this->moderator)->put("{$url}/{$finding->id}", ['status' => 'done']);
        $this->assertSame('done', $finding->fresh()->status);
        $this->actingAs($this->moderator)->delete("{$url}/{$finding->id}");
        $this->assertSame(1, $exercise->findings()->count());
    }

    public function test_the_review_shows_the_plan_coverage_of_the_exercise(): void
    {
        $exercise = $this->exercise();
        $this->control($exercise, 'session/start');
        $this->control($exercise, 'release', ['inject' => 'inj-1']);
        $release = $exercise->releases()->sole();
        $cited = $this->plan->sections()->whereIn('concept', ['reporting_procedure', 'containment'])->pluck('id')->all();
        $this->actingAs($this->player)->post("/exercises/{$exercise->id}/responses", ['inject_release_id' => $release->id, 'body' => 'Report and contain.', 'sections' => $cited]);

        $coverage = ReviewController::coverage($exercise);
        $this->assertSame(['Reporting a Suspected Incident'], array_map(fn ($section) => $section->title, $coverage['used']));
        $this->assertSame(['Severity Table'], array_map(fn ($section) => $section->title, $coverage['missed']));
        $this->assertSame(['Containment'], array_map(fn ($section) => $section->title, $coverage['extra']));
        $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/review")->assertOk()->assertSeeText('Missed (expected, not cited): 2 Severity Table');
    }

    public function test_the_after_action_report_becomes_final_with_an_evidence_package(): void
    {
        Storage::fake('local');
        $exercise = $this->exercise();
        $this->control($exercise, 'session/start');
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/logs/communication", ['direction' => 'external', 'summary' => '=HYPERLINK("http://x")']);
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/review/marks", ['marks' => ['inj-1' => [['result' => 'met'], ['result' => 'met']]]]);
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/findings", ['type' => 'strength', 'text' => 'Fast report to security.']);

        $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/report")->assertOk()
            ->assertSee('After-action report: '.$exercise->title)->assertSee('Pat Player')->assertSee('Fast report to security.');
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/report", ['tlp' => 'TLP:GREEN', 'summary' => 'A phishing email led to a mailbox rule.', 'phases' => ['detection' => 'The team found the rule fast.']])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/report/final")->assertSessionHasErrors('report');

        $this->control($exercise, 'session/end');
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/report/final")->assertSessionHasNoErrors();
        $report = $exercise->reports()->sole();
        $this->assertSame(['final', 'TLP:GREEN', $this->moderator->name], [$report->status, $report->tlp, $report->content['closure']['approver']]);
        $this->assertSame('The team found the rule fast.', $report->content['phases'][0]['text']);
        $this->assertStringStartsWith('%PDF', (string) Storage::get($report->folder().'/aar.pdf'));
        $this->assertSame(hash('sha256', (string) Storage::get($report->folder().'/aar.pdf')), $report->file_hash);
        $this->assertStringContainsString("'=HYPERLINK", (string) Storage::get($report->folder().'/communication.csv'));
        $this->assertStringContainsString('## 3.6.3', (string) Storage::get($report->folder().'/mapping.md'));

        // A final version does not change.
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/report", ['tlp' => 'TLP:RED'])->assertStatus(409);

        $download = $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/reports/{$report->id}/evidence.zip");
        $download->assertOk();
        $zip = new ZipArchive;
        $zip->open($download->baseResponse->getFile()->getPathname());
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertSame(['aar.pdf', 'action.csv', 'communication.csv', 'logs.pdf', 'mapping.md', 'scenario.yaml', 'validation.csv'], array_keys($manifest['files']));
        foreach ($manifest['files'] as $name => $hash) {
            $this->assertSame($hash, hash('sha256', (string) $zip->getFromName($name)), $name);
        }
        $this->assertStringContainsString($report->file_hash.'  aar.pdf', (string) $zip->getFromName('SHA256SUMS'));
        $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/reports/{$report->id}/aar.pdf")->assertOk();

        // A change after Final is a new version.
        $this->actingAs($this->moderator)->post("/exercises/{$exercise->id}/report/new-version");
        $draft = $exercise->reports()->first();
        $this->assertSame([2, 'draft', 'A phishing email led to a mailbox rule.', 'The team found the rule fast.'], [$draft->version, $draft->status, $draft->content['summary'], $draft->content['phases']['detection']]);
        $this->actingAs($this->player)->get("/exercises/{$exercise->id}/report")->assertForbidden();
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

    /**
     * Guest access on, then join as a guest (logged out first). Returns the guest.
     */
    private function joinAsGuest(Exercise $exercise, string $name = 'Gale Guest'): User
    {
        $this->control($exercise, 'guests/on')->assertSessionHasNoErrors();
        $code = (string) $exercise->refresh()->guest_code;
        auth()->guard('web')->forgetUser();

        // Lower case and spaces are accepted.
        $this->post('/join', ['code' => strtolower(substr($code, 0, 5).' '.substr($code, 5)), 'name' => $name])
            ->assertRedirect("/exercises/{$exercise->id}/live");

        return User::query()->where('name', $name)->sole();
    }

    public function test_a_guest_joins_with_the_session_code_and_sees_only_the_live_screen(): void
    {
        $exercise = $this->exercise();
        $other = $this->exercise();
        $guest = $this->joinAsGuest($exercise);

        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{10}$/', (string) $exercise->guest_code);
        $this->assertTrue($guest->isGuest());
        $this->assertSame('participant', $exercise->participants()->where('user_id', $guest->id)->sole()->role->value);
        $this->assertAuthenticatedAs($guest);

        $this->actingAs($guest)->get("/exercises/{$exercise->id}/live")->assertOk()->assertSee($exercise->title);
        $this->actingAs($guest)->get('/plans')->assertRedirect("/exercises/{$exercise->id}/live");
        $this->actingAs($guest)->get('/account')->assertRedirect("/exercises/{$exercise->id}/live");
        $this->actingAs($guest)->get("/exercises/{$other->id}/live")->assertRedirect("/exercises/{$exercise->id}/live");
        $this->actingAs($guest)->post("/exercises/{$other->id}/confirm")->assertForbidden();
        $this->actingAs($guest)->put('/user/password', [])->assertForbidden();
    }

    public function test_a_wrong_code_is_refused_and_the_end_of_the_session_ends_guest_access(): void
    {
        $exercise = $this->exercise();
        $this->post('/join', ['code' => 'WRONGCODE2', 'name' => 'X'])->assertSessionHasErrors('code');
        $guest = $this->joinAsGuest($exercise);
        $code = $exercise->guest_code;

        $this->control($exercise, 'session/start');
        $this->control($exercise, 'session/end');
        $this->assertNull($exercise->refresh()->guest_code);

        $this->actingAs($guest)->get("/exercises/{$exercise->id}/live")->assertRedirect('/join');
        $this->assertGuest();
        $this->post('/join', ['code' => $code, 'name' => 'Late Guest'])->assertSessionHasErrors('code');
        $this->control($exercise, 'guests/on')->assertSessionHasErrors('guests');
    }

    public function test_guests_get_a_plan_role_and_are_not_in_the_user_lists(): void
    {
        $exercise = $this->exercise();
        $guest = $this->joinAsGuest($exercise);
        $participant = $exercise->participants()->where('user_id', $guest->id)->sole();

        $this->actingAs($this->moderator)->put("/exercises/{$exercise->id}/participants/{$participant->id}", ['plan_role' => 'Legal'])->assertSessionHasNoErrors();
        $this->assertSame('Legal', $participant->refresh()->plan_role);
        $this->actingAs($this->moderator)->get("/exercises/{$exercise->id}/setup")->assertOk()->assertSee('(guest)');
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create())->get('/admin/users')->assertOk()->assertDontSee('Gale Guest');

        $this->control($exercise, 'guests/off');
        $this->assertNull($exercise->refresh()->guest_code);

        // Deleting the exercise deletes its guest accounts.
        $this->actingAs($this->moderator)->delete("/exercises/{$exercise->id}");
        $this->assertModelMissing($guest);
    }
}
