<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use App\Enums\ExerciseRole;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An inject that the moderator released, stored exactly as participants saw it.
 */
class InjectRelease extends Model
{
    protected function casts(): array
    {
        return ['artifacts' => 'array', 'questions' => 'array', 'audience' => 'array'];
    }

    /**
     * An inject for everybody: no audience, or the audience "all".
     */
    public function isForAll(): bool
    {
        return empty($this->audience) || array_intersect(['all', 'everyone'], array_map('strtolower', $this->audience)) !== [];
    }

    /**
     * Who sees the inject (backlog B9). The audience lists plan roles (for example CISO)
     * or exercise roles (participant, scribe, observer). Moderators and scribes see every inject.
     */
    public function isVisibleTo(?Participant $participant, bool $moderator): bool
    {
        if ($moderator || $this->isForAll() || $participant?->role === ExerciseRole::Scribe) {
            return true;
        }
        $audience = array_map(fn ($name) => strtolower(trim((string) $name)), $this->audience ?? []);

        return $participant !== null
            && (in_array($participant->role->value, $audience, true) || in_array(strtolower((string) $participant->plan_role), $audience, true));
    }

    /**
     * The end of the inject timer (backlog B10), or null without a timer.
     */
    public function deadline(): ?CarbonInterface
    {
        return $this->timer_minutes ? $this->created_at->copy()->addMinutes($this->timer_minutes) : null;
    }

    /**
     * @return HasMany<Response, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class)->orderBy('id');
    }
}
