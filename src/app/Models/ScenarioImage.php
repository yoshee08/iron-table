<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use App\Jobs\GenerateScenarioImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One AI picture request. Status: queued, working, done or failed.
 */
class ScenarioImage extends Model
{
    /**
     * @return BelongsTo<Scenario, $this>
     */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    /**
     * Ask the image provider for a picture for one artifact. The queue worker makes it.
     */
    public static function request(Scenario $scenario, string $inject, int $artifact, string $prompt, int $userId): self
    {
        $image = new self;
        $image->forceFill([
            'scenario_id' => $scenario->id,
            'inject_key' => $inject,
            'artifact' => $artifact,
            'prompt' => $prompt,
            'created_by' => $userId,
        ])->save();
        GenerateScenarioImage::dispatch($image);

        return $image;
    }
}
