<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->withTwoFactor()->create();
    }

    public function test_an_admin_sees_the_user_list(): void
    {
        $other = User::factory()->create(['name' => 'Pat Example']);

        $this->actingAs($this->admin())->get('/admin/users')
            ->assertOk()
            ->assertSee('Pat Example')
            ->assertSee($other->email);
    }

    public function test_only_an_admin_can_manage_users(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
        $this->actingAs(User::factory()->moderator()->withTwoFactor()->create())->get('/admin/users')->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->post('/admin/users', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'admin'])
            ->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_an_admin_adds_a_user_and_gets_a_working_setup_link(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'New Moderator',
            'email' => 'new@example.com',
            'role' => 'moderator',
        ]);

        $response->assertRedirect('/admin/users')->assertSessionHas('setupLink');
        $user = User::query()->where('email', 'new@example.com')->sole();
        $this->assertSame(Role::Moderator, $user->role);

        // The setup link is a password reset link: the new user sets a password with it.
        $link = session('setupLink');
        $this->assertStringContainsString('/reset-password/', $link);
        $token = basename(parse_url($link, PHP_URL_PATH));
        $this->post('/logout');
        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'new@example.com',
            'password' => 'a-new-long-password',
            'password_confirmation' => 'a-new-long-password',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('a-new-long-password', $user->fresh()->password));
    }

    public function test_an_email_can_be_used_only_once(): void
    {
        $existing = User::factory()->create();

        $this->actingAs($this->admin())
            ->post('/admin/users', ['name' => 'Copy', 'email' => $existing->email, 'role' => 'user'])
            ->assertSessionHasErrors('email');
    }

    public function test_an_admin_changes_the_role_of_another_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->put("/admin/users/{$user->id}/role", ['role' => 'moderator'])
            ->assertRedirect('/admin/users');

        $this->assertSame(Role::Moderator, $user->fresh()->role);
    }

    public function test_an_admin_cannot_change_their_own_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/admin/users/{$admin->id}/role", ['role' => 'user'])
            ->assertSessionHasErrors('role');

        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_an_admin_deletes_another_user_but_not_themselves(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->delete("/admin/users/{$user->id}")->assertRedirect('/admin/users');
        $this->assertModelMissing($user);

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")->assertSessionHasErrors('user');
        $this->assertModelExists($admin);
    }

    public function test_an_admin_makes_a_new_setup_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->post("/admin/users/{$user->id}/setup-link")
            ->assertRedirect('/admin/users')
            ->assertSessionHas('setupLink');
    }
}
