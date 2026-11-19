<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Something changed in a live exercise. Open pages reload their live part
 * (resources/js/live.js). The event carries no exercise data.
 */
class ExerciseChanged implements ShouldBroadcastNow
{
    public function __construct(public int $exerciseId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('exercise.'.$this->exerciseId);
    }

    public function broadcastAs(): string
    {
        return 'changed';
    }
}
