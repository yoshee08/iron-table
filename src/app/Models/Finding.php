<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A strength, a weakness or a plan gap, with an optional corrective action (backlog B5).
 */
class Finding extends Model
{
    public const TYPES = ['strength' => 'Strength', 'weakness' => 'Weakness', 'plan_gap' => 'Plan gap'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_date?->isPast() === true;
    }
}
