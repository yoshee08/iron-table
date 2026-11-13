<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

/*
 * IRon Table settings. Values come from .env (see .env.example).
 */

return [

    /*
     * The first administrator account (spec section 11.3, item 5).
     * The app container creates it on the first start, only when the
     * users table is empty. scripts/setup.sh writes these values.
     */
    'initial_admin' => [
        'name' => env('INITIAL_ADMIN_NAME', 'Administrator'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],

];
