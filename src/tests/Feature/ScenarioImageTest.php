<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Models\Scenario;
use App\Models\ScenarioImage;
use App\Models\User;
use App\Services\Ai\ImageProvider;
use App\Services\Ai\Provider;
use App\Services\ScenarioChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\FakeAi;
use Tests\FakeImages;
use Tests\TestCase;
use ZipArchive;

class ScenarioImageTest extends TestCase
{
    use RefreshDatabase;

    private User $moderator;

    private Scenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->moderator = User::factory()->moderator()->withTwoFactor()->create();
        $this->scenario = new Scenario;
        $this->scenario->forceFill(['slug' => 'phish-to-mailbox-rule', 'version' => '1.0.0', 'title' => 'Phishing',
            'source' => (string) file_get_contents(resource_path('scenarios/phish-to-mailbox-rule.yaml'))])->save();
    }

    /**
     * @return array<string, mixed> the picture artifact of inj-1 (the last artifact)
     */
    private function picture(): array
    {
        $artifacts = (new ScenarioChecker)->read($this->scenario->fresh()->source)['injects'][0]['artifacts'];

        return end($artifacts);
    }

    private function images(?string $error = null): FakeImages
    {
        config(['iron-table.images.provider' => 'local']);
        $images = new FakeImages($error);
        $this->app->instance(ImageProvider::class, $images);

        return $images;
    }

    public function test_a_moderator_uploads_a_picture(): void
    {
        $this->actingAs($this->moderator)->post("/scenarios/{$this->scenario->id}/pictures/inj-1", [
            'title' => 'Server room door',
            'file' => UploadedFile::fake()->createWithContent('door.png', FakeImages::png()),
        ])->assertRedirect("/scenarios/{$this->scenario->id}#inj-1");

        $picture = $this->picture();
        $this->assertSame(['image', 'Server room door'], [$picture['type'], $picture['title']]);
        Storage::assertExists("scenario-images/{$this->scenario->id}/{$picture['image']}");
        $this->actingAs($this->moderator)->get("/scenarios/{$this->scenario->id}")->assertOk()
            ->assertSee("/scenarios/{$this->scenario->id}/images/{$picture['image']}");
        $this->actingAs($this->moderator)->get("/scenarios/{$this->scenario->id}/images/{$picture['image']}")->assertOk();
        $this->actingAs(User::factory()->create())->get("/scenarios/{$this->scenario->id}/images/{$picture['image']}")->assertForbidden();
    }

    public function test_the_image_service_makes_the_picture_and_the_moderator_chooses_it(): void
    {
        $images = $this->images();

        $this->actingAs($this->moderator)->post("/scenarios/{$this->scenario->id}/pictures/inj-1", [
            'title' => 'Open door', 'prompt' => 'An office door propped open with a chair at night',
        ]);
        $made = ScenarioImage::query()->sole();
        $this->assertSame(['done', ['An office door propped open with a chair at night']], [$made->status, $images->prompts]);
        $this->assertArrayNotHasKey('image', $this->picture());
        $this->actingAs($this->moderator)->get("/scenarios/{$this->scenario->id}")->assertOk()
            ->assertSee('is a picture without an image')
            ->assertSee('Use this picture');

        $this->actingAs($this->moderator)->post("/scenarios/{$this->scenario->id}/images/{$made->id}/choose");
        $this->assertSame($made->file, $this->picture()['image']);
        $this->actingAs($this->moderator)->get("/scenarios/{$this->scenario->id}")->assertOk()->assertDontSee('is a picture without an image');

        // Another picture for the same prompt.
        $this->actingAs($this->moderator)->post("/scenarios/{$this->scenario->id}/pictures/inj-1/1/generate");
        $this->assertSame(2, ScenarioImage::query()->where('status', 'done')->count());
    }

    public function test_a_failed_picture_shows_the_reason(): void
    {
        $this->images('IRon Table cannot reach the local image service.');

        $this->actingAs($this->moderator)->post("/scenarios/{$this->scenario->id}/pictures/inj-1", ['title' => 'Door', 'prompt' => 'A door']);
        $this->assertSame(['failed', 'IRon Table cannot reach the local image service.'], [ScenarioImage::query()->sole()->status, ScenarioImage::query()->sole()->error]);
        $this->actingAs($this->moderator)->get("/scenarios/{$this->scenario->id}")->assertSee('Failed: IRon Table cannot reach the local image service.');
    }

    public function test_without_an_image_provider_the_prompt_waits(): void
    {
        $this->actingAs($this->moderator)->post("/scenarios/{$this->scenario->id}/pictures/inj-1", ['title' => 'Door', 'prompt' => 'A door']);

        $this->assertSame(0, ScenarioImage::query()->count());
        $this->assertSame('A door', $this->picture()['prompt']);
        $this->actingAs($this->moderator)->post("/scenarios/{$this->scenario->id}/pictures/inj-1/1/generate")->assertForbidden();
        $this->actingAs($this->moderator)->post("/scenarios/{$this->scenario->id}/pictures/inj-1", ['title' => 'Nothing'])->assertSessionHasErrors('prompt');
    }

    public function test_the_ai_writes_the_picture_prompt_and_the_picture_is_made(): void
    {
        config(['iron-table.ai.enabled' => true]);
        $this->app->instance(Provider::class, new FakeAi([['title' => 'Forwarding rule on screen', 'prompt' => 'A laptop on a desk at night, screen glowing, seen from behind']]));
        $images = $this->images();

        $this->actingAs($this->moderator)->post("/scenarios/{$this->scenario->id}/ai/inj-1/picture");

        $this->assertSame('A laptop on a desk at night, screen glowing, seen from behind', $this->picture()['prompt']);
        $this->assertSame(['A laptop on a desk at night, screen glowing, seen from behind'], $images->prompts);
        $this->assertSame('done', ScenarioImage::query()->sole()->status);
    }

    public function test_a_scenario_with_pictures_exports_and_imports_as_zip(): void
    {
        $this->actingAs($this->moderator)->post("/scenarios/{$this->scenario->id}/pictures/inj-1", [
            'title' => 'Door', 'file' => UploadedFile::fake()->createWithContent('door.png', FakeImages::png()),
        ]);
        $name = $this->picture()['image'];

        $download = $this->actingAs($this->moderator)->get("/scenarios/{$this->scenario->id}/download");
        $download->assertOk()->assertDownload('phish-to-mailbox-rule-1.0.0.zip');
        $copy = tempnam(sys_get_temp_dir(), 'zip');
        copy($download->baseResponse->getFile()->getPathname(), $copy);
        $zip = new ZipArchive;
        $zip->open($copy);
        $this->assertSame($this->scenario->fresh()->source, $zip->getFromName('scenario.yaml'));
        $this->assertSame(FakeImages::png(), $zip->getFromName("images/$name"));
        $zip->close();

        $this->actingAs($this->moderator)->delete("/scenarios/{$this->scenario->id}");
        Storage::assertMissing("scenario-images/{$this->scenario->id}/$name");

        $this->actingAs($this->moderator)->post('/scenarios', ['file' => new UploadedFile($copy, 'scenario.zip', 'application/zip', null, true)])
            ->assertSessionHasNoErrors();
        $imported = Scenario::query()->sole();
        Storage::assertExists("scenario-images/{$imported->id}/$name");
    }

    public function test_a_picture_name_cannot_leave_its_folder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('image must be a file name such as door.png');

        (new ScenarioChecker)->read(str_replace("      - type: ticket\n", "      - type: image\n        image: ../../.env.png\n      - type: ticket\n", $this->scenario->source));
    }
}
