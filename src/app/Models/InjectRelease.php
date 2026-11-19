<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An inject that the moderator released, stored exactly as participants saw it.
 */
class InjectRelease extends Model
{
    protected function casts(): array
    {
        return ['artifacts' => 'array', 'questions' => 'array'];
    }

    /**
     * @return HasMany<Response, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class)->orderBy('id');
    }
}
