<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Laravel\Fortify\Features;

/*
 * Only the Fortify settings that differ from the package defaults.
 * No self-registration: an administrator creates the accounts (spec section 3).
 */
return [

    'home' => '/dashboard',

    'features' => [
        Features::resetPasswords(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],

];
