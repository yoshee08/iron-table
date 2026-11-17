<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The page gives the browser the public Reverb key at run time
 * (resources/views/partials/reverb-meta.blade.php). The secret must never be sent.
 */
class ReverbMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_contains_the_public_reverb_key(): void
    {
        config([
            'broadcasting.connections.reverb.key' => 'public-test-key',
            'broadcasting.connections.reverb.secret' => 'secret-test-value',
        ]);

        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertOk();
        $response->assertSee('<meta name="reverb-key" content="public-test-key">', false);
        $response->assertDontSee('secret-test-value', false);
    }
}
