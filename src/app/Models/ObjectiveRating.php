<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The HSEEP rating of one exercise objective (backlog B4). A rating needs a justification (spec section 8.3).
 */
class ObjectiveRating extends Model
{
    public const RATINGS = [
        'performed' => 'Performed without challenges',
        'some' => 'Performed with some challenges',
        'major' => 'Performed with major challenges',
        'unable' => 'Unable to be performed',
    ];
}
