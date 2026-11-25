<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One version of the after-action report. Status: draft or final. A final report
 * never changes: "content" holds the full report and the evidence files are stored.
 */
class Report extends Model
{
    /** Traffic Light Protocol labels (backlog B8). */
    public const TLP = ['TLP:CLEAR', 'TLP:GREEN', 'TLP:AMBER', 'TLP:AMBER+STRICT', 'TLP:RED'];

    protected function casts(): array
    {
        return ['content' => 'array', 'approved_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Exercise, $this>
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isFinal(): bool
    {
        return $this->status === 'final';
    }

    /**
     * The private storage folder of the evidence files of this version.
     */
    public function folder(): string
    {
        return 'reports/'.$this->exercise_id.'/v'.$this->version;
    }
}
