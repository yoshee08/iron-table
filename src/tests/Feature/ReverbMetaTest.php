<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The page gives the browser the public Reverb key at run time
 * (resources/views/partials/reverb-meta.blade.php). The secret must never be sent.
 */
class ReverbMetaTest extends TestCase
{
    public function test_page_contains_the_public_reverb_key(): void
    {
        config([
            'broadcasting.connections.reverb.key' => 'public-test-key',
            'broadcasting.connections.reverb.secret' => 'secret-test-value',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<meta name="reverb-key" content="public-test-key">', false);
        $response->assertDontSee('secret-test-value', false);
    }
}
