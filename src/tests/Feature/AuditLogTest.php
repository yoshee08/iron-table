<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_logins_failed_logins_and_logouts_are_recorded_without_the_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password-123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/logout');

        $this->assertSame(['login.failed', 'login', 'logout'], AuditEvent::query()->orderBy('id')->pluck('action')->all());
        $this->assertSame($user->email, AuditEvent::query()->where('action', 'login.failed')->value('actor'));
        $this->assertSame($user->id, AuditEvent::query()->where('action', 'login')->value('actor_id'));
        $this->assertStringNotContainsString('wrong-password-123', AuditEvent::query()->pluck('data')->implode(' '));
    }

    public function test_a_change_is_recorded_with_the_route_parameters_but_not_the_input(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $other = User::factory()->create();

        $this->actingAs($admin)->put("/admin/users/{$other->id}/role", ['role' => 'moderator']);

        $event = AuditEvent::query()->where('action', 'admin.users.role')->sole();
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame(['user' => $other->id, 'status' => 302], $event->details());
        $this->assertStringNotContainsString('moderator', $event->data);
    }

    public function test_reading_a_page_is_not_recorded(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();

        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_a_refused_change_is_recorded_with_its_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/admin/users', ['name' => 'X', 'email' => 'x@example.com', 'role' => 'admin'])->assertForbidden();

        $this->assertSame(403, AuditEvent::query()->where('action', 'admin.users.store')->sole()->details()['status']);
    }

    public function test_the_chain_is_complete_and_a_changed_event_breaks_it(): void
    {
        $audit = app(AuditLog::class);
        $first = $audit->record('test.one', ['a' => 1]);
        $second = $audit->record('test.two');
        $audit->record('test.three');

        $this->assertSame(AuditLog::FIRST_HASH, $first->previous_hash);
        $this->assertSame($first->hash, $second->previous_hash);
        $this->assertSame(['count' => 3, 'broken' => null], $audit->verify());

        // Somebody with database access changes an event (the trigger must be off for this).
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('ALTER TABLE audit_events DISABLE TRIGGER audit_events_no_change');
        }
        DB::table('audit_events')->where('id', $second->id)->update(['actor' => 'somebody-else']);

        $this->assertSame(['count' => 2, 'broken' => $second->id], $audit->verify());
        $this->artisan('iron-table:audit-verify')->assertFailed();
    }

    public function test_the_database_stops_updates_and_deletes(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('The trigger is PostgreSQL only.');
        }
        $event = app(AuditLog::class)->record('test.one');

        try {
            DB::table('audit_events')->where('id', $event->id)->delete();
            $this->fail('The delete was not stopped.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
    }

    public function test_only_an_admin_sees_the_audit_log_and_checks_the_chain(): void
    {
        app(AuditLog::class)->record('test.one', [], null, 'console');

        $this->actingAs(User::factory()->moderator()->withTwoFactor()->create())->get('/admin/audit')->assertForbidden();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create())->get('/admin/audit?check=1')
            ->assertOk()
            ->assertSee('test.one')
            ->assertSee('Events checked: 1. The chain is complete.');
        $this->artisan('iron-table:audit-verify')->assertSuccessful();
    }
}
