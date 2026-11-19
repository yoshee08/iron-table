<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Creates the first administrator on the first start (spec section 11.3, item 5).
 *
 * - Users exist:            do nothing (exit 0).
 * - No settings in .env:    do nothing (exit 0).
 * - Settings not valid:     stop with an error (exit 1), so the problem is seen.
 *
 * The account gets the Administrator role.
 */
class CreateFirstAdmin extends Command
{
    protected $signature = 'iron-table:first-admin';

    protected $description = 'Create the first administrator when no user exists';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->info('First administrator: users exist. Nothing to do.');

            return self::SUCCESS;
        }

        $settings = config('iron-table.initial_admin');

        if (blank($settings['email'] ?? null) && blank($settings['password'] ?? null)) {
            $this->warn('First administrator: INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD are not set. Nothing to do.');

            return self::SUCCESS;
        }

        $validator = Validator::make($settings, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error('First administrator: '.$message);
            }

            return self::FAILURE;
        }

        User::query()->forceCreate([
            'name' => $settings['name'],
            'email' => $settings['email'],
            'password' => $settings['password'],
            'role' => Role::Admin,
        ]);

        $this->info('First administrator created: '.$settings['email']);

        return self::SUCCESS;
    }
}
