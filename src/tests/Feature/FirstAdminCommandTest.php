<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FirstAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private function setAdmin(?string $email, ?string $password, string $name = 'Administrator'): void
    {
        config(['iron-table.initial_admin' => compact('name', 'email', 'password')]);
    }

    public function test_it_creates_the_first_administrator(): void
    {
        $this->setAdmin('admin@example.com', 'correct-horse-battery');

        $this->artisan('iron-table:first-admin')->assertSuccessful();

        $user = User::query()->sole();
        $this->assertSame('admin@example.com', $user->email);
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));
    }

    public function test_it_does_nothing_when_a_user_exists(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);
        $this->setAdmin('admin@example.com', 'correct-horse-battery');

        $this->artisan('iron-table:first-admin')->assertSuccessful();

        $this->assertSame(1, User::query()->count());
        $this->assertFalse(User::query()->where('email', 'admin@example.com')->exists());
    }

    public function test_it_does_nothing_when_no_settings_exist(): void
    {
        $this->setAdmin(null, null);

        $this->artisan('iron-table:first-admin')->assertSuccessful();

        $this->assertSame(0, User::query()->count());
    }

    public function test_it_fails_with_a_short_password(): void
    {
        $this->setAdmin('admin@example.com', 'short');

        $this->artisan('iron-table:first-admin')->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
