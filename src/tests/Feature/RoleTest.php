<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_has_the_user_role(): void
    {
        $this->assertSame(Role::User, User::factory()->create()->fresh()->role);
    }

    public function test_the_role_cannot_be_mass_assigned(): void
    {
        $user = new User(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x', 'role' => Role::Admin]);

        $this->assertNull($user->role);
    }

    public function test_gates_follow_the_role(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $user = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('admin'));
        $this->assertTrue(Gate::forUser($admin)->allows('moderate'));
        $this->assertFalse(Gate::forUser($moderator)->allows('admin'));
        $this->assertTrue(Gate::forUser($moderator)->allows('moderate'));
        $this->assertFalse(Gate::forUser($user)->allows('admin'));
        $this->assertFalse(Gate::forUser($user)->allows('moderate'));
    }

    public function test_the_page_shows_the_role(): void
    {
        $this->actingAs(User::factory()->moderator()->withTwoFactor()->create())
            ->get('/dashboard')
            ->assertSee('(Moderator)');
    }
}
