<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Jobs;

use App\Models\ScenarioImage;
use App\Services\Ai\AiException;
use App\Services\Ai\ImageProvider;
use finfo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Makes one picture in the queue worker. A local CPU model can take many minutes.
 */
class GenerateScenarioImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    private const TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(public ScenarioImage $image)
    {
        $this->timeout = config('iron-table.images.timeout') + 60;
    }

    public function handle(ImageProvider $images): void
    {
        $this->image->forceFill(['status' => 'working'])->save();

        try {
            $picture = $images->generate($this->image->prompt);
        } catch (AiException $e) {
            $this->image->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();

            return;
        }

        $extension = self::TYPES[(new finfo(FILEINFO_MIME_TYPE))->buffer($picture)] ?? null;
        if ($extension === null) {
            $this->image->forceFill(['status' => 'failed', 'error' => 'The image service gave a file that is not a PNG, JPEG or WebP picture.'])->save();

            return;
        }

        $name = Str::lower(Str::random(24)).'.'.$extension;
        Storage::put(self::folder($this->image->scenario_id).'/'.$name, $picture);
        $this->image->forceFill(['status' => 'done', 'file' => $name])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $this->image->forceFill(['status' => 'failed', 'error' => 'The picture took too long, or the queue worker stopped. Try again.'])->save();
    }

    /**
     * The private storage folder of the pictures of a scenario.
     */
    public static function folder(int $scenarioId): string
    {
        return 'scenario-images/'.$scenarioId;
    }
}
