<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The mark of one rubric item (spec section 8.3): met, partly or not_met.
 */
#[Fillable(['exercise_id', 'inject_key', 'item', 'result', 'points', 'reason', 'marked_by'])]
class RubricMark extends Model
{
    public const RESULTS = ['met' => 'Met', 'partly' => 'Partly met', 'not_met' => 'Not met'];

    /**
     * The usual points for a result: all, half (rounded down) or none.
     */
    public static function pointsFor(string $result, int $points): int
    {
        return match ($result) {
            'met' => $points,
            'partly' => intdiv($points, 2),
            default => 0,
        };
    }
}
