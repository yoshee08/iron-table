<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use App\Enums\ExerciseRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One exercise and its live session (spec sections 4 and 10).
 *
 * Status: prepared, running, paused, stopped (real-world emergency, backlog B7) or ended.
 */
class Exercise extends Model
{
    public const LIVE = ['running', 'paused', 'stopped'];

    protected function casts(): array
    {
        return [
            'scenario' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Participant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /**
     * @return HasMany<InjectRelease, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(InjectRelease::class)->orderByDesc('id');
    }

    /**
     * @return HasMany<Response, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class)->orderByDesc('id');
    }

    /**
     * @return HasMany<LogEntry, $this>
     */
    public function logEntries(): HasMany
    {
        return $this->hasMany(LogEntry::class)->orderByDesc('id');
    }

    /**
     * @return BelongsTo<IrPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(IrPlan::class, 'ir_plan_id');
    }

    public function roleOf(User $user): ?ExerciseRole
    {
        return $this->participants->firstWhere('user_id', $user->id)?->role;
    }

    /**
     * Participants submit responses while the session runs.
     */
    public function acceptsResponsesFrom(User $user): bool
    {
        return $this->status === 'running' && $this->roleOf($user) === ExerciseRole::Participant;
    }

    /**
     * A simulated time as minutes from the start of day 1, to compare two times.
     */
    public static function minutes(int $day, string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($day * 24 + $hours) * 60 + $minutes;
    }

    /**
     * The simulated clock as text, for example "day 2 09:47" or "09:47".
     */
    public function simClock(): string
    {
        if ($this->sim_time === null) {
            return 'not started';
        }

        return ($this->sim_day > 1 ? 'day '.$this->sim_day.' ' : '').$this->sim_time;
    }
}
