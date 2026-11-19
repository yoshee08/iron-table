<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

/**
 * Application role of a user (spec section 3). One organization per install.
 * The roles in an exercise (moderator, participant, scribe, observer) are set
 * per exercise, not here.
 */
enum Role: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Moderator => 'Moderator',
            self::User => 'User',
        };
    }
}
