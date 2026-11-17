<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Models\IrPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function moderator(): User
    {
        return User::factory()->moderator()->withTwoFactor()->create();
    }

    private function upload(User $user, string $file = 'example-plan.md'): TestResponse
    {
        $path = base_path('tests/Fixtures/plans/'.$file);

        return $this->actingAs($user)->post('/plans', [
            'title' => 'Example Corp IR Plan',
            'file' => new UploadedFile($path, $file, null, null, true),
        ]);
    }

    public function test_a_moderator_uploads_a_plan_and_it_is_split_into_sections(): void
    {
        $response = $this->upload($this->moderator(), 'example-plan.docx');

        $plan = IrPlan::query()->sole();
        $response->assertRedirect("/plans/{$plan->id}");
        $this->assertSame(1, $plan->version);
        $this->assertSame(['1', '2', '2.1', '3', '4', '4.1'], $plan->sections->pluck('number')->filter()->values()->all());
        Storage::disk('local')->assertExists($plan->file_path);
        $this->assertStringStartsWith('plans/', $plan->file_path);
    }

    public function test_a_user_reads_but_cannot_upload_or_change_the_plan(): void
    {
        $this->upload($this->moderator());
        $plan = IrPlan::query()->sole();
        $section = $plan->sections->first();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/plans/{$plan->id}")->assertOk()->assertSee('Reporting a Suspected Incident');
        $this->actingAs($user)->get('/plans/create')->assertForbidden();
        $this->upload($user)->assertForbidden();
        $this->actingAs($user)->put("/plans/{$plan->id}/sections/{$section->id}", ['title' => 'X'])->assertForbidden();
        $this->assertSame(1, IrPlan::query()->count());
    }

    public function test_search_finds_the_matching_section(): void
    {
        $this->upload($this->moderator());
        $plan = IrPlan::query()->sole();

        $this->actingAs(User::factory()->create())
            ->get("/plans/{$plan->id}?q=severity+days")
            ->assertOk()
            ->assertSee('Severity Table')
            ->assertDontSee('Locations');
    }

    public function test_a_new_upload_is_the_next_version(): void
    {
        $moderator = $this->moderator();
        $this->upload($moderator);
        $this->upload($moderator, 'example-plan.pdf');

        $this->assertSame([1, 2], IrPlan::query()->orderBy('version')->pluck('version')->all());
        $this->assertSame(2, IrPlan::current()->version);
    }

    public function test_an_unreadable_file_is_refused(): void
    {
        $moderator = $this->moderator();
        $broken = UploadedFile::fake()->createWithContent('plan.pdf', 'this is not a PDF');

        $this->actingAs($moderator)->post('/plans', ['file' => $broken])->assertSessionHasErrors('file');
        $this->actingAs($moderator)->post('/plans', ['file' => UploadedFile::fake()->create('plan.exe', 10)])->assertSessionHasErrors('file');
        $this->assertSame(0, IrPlan::query()->count());
    }

    public function test_a_moderator_corrects_and_deletes_a_section(): void
    {
        $moderator = $this->moderator();
        $this->upload($moderator);
        $plan = IrPlan::query()->sole();
        $section = $plan->sections->firstWhere('number', '4.1');

        $this->actingAs($moderator)
            ->put("/plans/{$plan->id}/sections/{$section->id}", ['number' => '4.2', 'title' => 'Severity levels table', 'body' => ''])
            ->assertRedirect("/plans/{$plan->id}");
        $this->assertSame('4.2', $section->fresh()->number);
        $this->assertSame('', $section->fresh()->body);

        $this->actingAs($moderator)->delete("/plans/{$plan->id}/sections/{$section->id}")->assertRedirect("/plans/{$plan->id}");
        $this->assertModelMissing($section);
    }

    public function test_a_section_of_another_plan_is_not_found(): void
    {
        $moderator = $this->moderator();
        $this->upload($moderator);
        $this->upload($moderator);
        [$first, $second] = IrPlan::query()->orderBy('version')->get();
        $section = $second->sections->first();

        $this->actingAs($moderator)->get("/plans/{$first->id}/sections/{$section->id}/edit")->assertNotFound();
    }

    public function test_the_original_file_can_be_downloaded(): void
    {
        $this->upload($this->moderator());
        $plan = IrPlan::query()->sole();

        $this->actingAs(User::factory()->create())
            ->get("/plans/{$plan->id}/download")
            ->assertOk()
            ->assertDownload('example-plan.md');
    }
}
