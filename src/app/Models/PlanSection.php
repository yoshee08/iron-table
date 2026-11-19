<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One numbered section of an IR plan, for example "5.2 Severity table".
 */
#[Fillable(['number', 'title', 'body'])]
#[Hidden(['search'])]
class PlanSection extends Model
{
    /**
     * @return BelongsTo<IrPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(IrPlan::class, 'ir_plan_id');
    }

    /**
     * Full-text search (PostgreSQL), best matches first.
     *
     * @param  Builder<PlanSection>  $query
     */
    public function scopeMatching(Builder $query, string $terms): void
    {
        $query->whereRaw("search @@ websearch_to_tsquery('english', ?)", [$terms])
            ->orderByRaw("ts_rank(search, websearch_to_tsquery('english', ?)) DESC", [$terms]);
    }
}
