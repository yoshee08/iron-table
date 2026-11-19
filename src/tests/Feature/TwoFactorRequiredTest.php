<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backlog B1: administrators and moderators must turn on two-factor authentication.
 */
class TwoFactorRequiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_without_two_factor_are_sent_to_the_account_page(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->moderator()->create()] as $staff) {
            $this->actingAs($staff)->get('/dashboard')
                ->assertRedirect('/account')
                ->assertSessionHas('status', 'two-factor-required');
        }
    }

    public function test_an_admin_without_two_factor_cannot_open_the_admin_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/users')
            ->assertRedirect('/account');
    }

    public function test_the_account_page_stays_open_to_turn_on_two_factor(): void
    {
        $this->actingAs(User::factory()->moderator()->create())
            ->withSession(['status' => 'two-factor-required'])
            ->get('/account')
            ->assertOk()
            ->assertSee('Your role needs two-factor authentication.');
    }

    public function test_staff_with_two_factor_can_work(): void
    {
        $this->actingAs(User::factory()->moderator()->withTwoFactor()->create())
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_a_user_without_two_factor_can_work(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();
    }
}
