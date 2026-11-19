<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A decision, action or answer from a participant, with the plan sections it cites.
 */
class Response extends Model
{
    protected function casts(): array
    {
        return ['no_coverage' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<PlanSection, $this>
     */
    public function citations(): BelongsToMany
    {
        return $this->belongsToMany(PlanSection::class, 'citations')->orderBy('position');
    }
}
