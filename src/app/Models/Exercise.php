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
     * @return HasMany<RubricMark, $this>
     */
    public function marks(): HasMany
    {
        return $this->hasMany(RubricMark::class);
    }

    /**
     * @return HasMany<ObjectiveRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(ObjectiveRating::class);
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class)->orderBy('id');
    }

    /**
     * Scores by IR phase and in total (spec section 8.3). The phase of a rubric item is
     * the "phase" of its inject. Items without a mark count as not met.
     *
     * @return array{phases: array<string, array{earned: int, possible: int}>, earned: int, possible: int, percent: ?int}
     */
    public function scores(): array
    {
        $marks = $this->marks->keyBy(fn (RubricMark $mark) => $mark->inject_key.'#'.$mark->item);
        $phases = [];
        foreach ($this->scenario['injects'] as $inject) {
            $phase = (string) ($inject['phase'] ?? 'other');
            foreach ($this->scenario['rubric'][$inject['id']] ?? [] as $n => $item) {
                $phases[$phase] ??= ['earned' => 0, 'possible' => 0];
                $phases[$phase]['possible'] += $item['points'];
                $phases[$phase]['earned'] += $marks->get($inject['id'].'#'.$n)?->points ?? 0;
            }
        }
        $earned = array_sum(array_column($phases, 'earned'));
        $possible = array_sum(array_column($phases, 'possible'));

        return ['phases' => $phases, 'earned' => $earned, 'possible' => $possible, 'percent' => $possible > 0 ? intdiv($earned * 100, $possible) : null];
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
