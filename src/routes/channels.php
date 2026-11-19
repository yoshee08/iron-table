<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

// Live updates of an exercise: its participants and the moderators.
Broadcast::channel('exercise.{exercise}', function (User $user, Exercise $exercise) {
    return Gate::forUser($user)->allows('moderate') || $exercise->roleOf($user) !== null;
});
