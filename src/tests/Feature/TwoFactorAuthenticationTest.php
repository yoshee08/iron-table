<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function currentCode(User $user): string
    {
        return (new Google2FA)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));
    }

    public function test_a_user_can_turn_on_two_factor_authentication(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/user/two-factor-authentication')
            ->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->two_factor_secret);

        $this->post('/user/confirmed-two-factor-authentication', ['code' => $this->currentCode($user)])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_turning_on_two_factor_authentication_needs_the_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/user/two-factor-authentication')
            ->assertRedirect('/user/confirm-password');
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_login_asks_for_the_code_and_accepts_it(): void
    {
        // Set up two-factor authentication directly: Fortify refuses a code
        // that was already used, so the login must use an unused code.
        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => encrypt((new Google2FA)->generateSecretKey()),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $this->currentCode($user)])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }
}
