<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One version of the organization's IR plan. The newest version is the current plan.
 */
class IrPlan extends Model
{
    /**
     * @return HasMany<PlanSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PlanSection::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function current(): ?self
    {
        return static::query()->orderByDesc('version')->first();
    }
}
