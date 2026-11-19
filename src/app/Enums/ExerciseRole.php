<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

/**
 * The role of a user in one exercise (spec section 3). Users with the account
 * role Moderator or Administrator control every exercise.
 */
enum ExerciseRole: string
{
    case Participant = 'participant';
    case Scribe = 'scribe';
    case Observer = 'observer';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
