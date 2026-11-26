<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Exercise;
use App\Models\User;
use App\Services\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An exercise that ended some days ago (null: never ended) and changed some days ago.
     */
    private function exercise(string $title, ?int $endedDaysAgo, int $changedDaysAgo): Exercise
    {
        $exercise = new Exercise;
        $exercise->forceFill([
            'title' => $title, 'scenario_version' => '1.0.0', 'scenario' => ['injects' => []],
            'status' => $endedDaysAgo === null ? 'prepared' : 'ended',
            'ended_at' => $endedDaysAgo === null ? null : now()->subDays($endedDaysAgo),
        ])->save();
        DB::table('exercises')->where('id', $exercise->id)->update(['updated_at' => now()->subDays($changedDaysAgo)]);
        Storage::put("exercise-images/{$exercise->id}/door.png", 'picture');
        Storage::put("reports/{$exercise->id}/v1/aar.pdf", 'report');

        return $exercise;
    }

    public function test_nothing_is_deleted_when_no_retention_period_is_set(): void
    {
        Storage::fake();
        config(['iron-table.retention_days' => 0]);
        $this->exercise('Old', 900, 900);

        $this->artisan('iron-table:retention')->expectsOutputToContain('RETENTION_DAYS is not set')->assertSuccessful();
        $this->assertSame(1, Exercise::query()->count());
    }

    public function test_old_exercises_with_their_files_and_guests_are_deleted_and_the_audit_log_is_kept(): void
    {
        Storage::fake();
        config(['iron-table.retention_days' => 365]);
        app(AuditLog::class)->record('test.before');
        $oldEnded = $this->exercise('Old ended', 400, 1);
        $oldPrepared = $this->exercise('Old never run', null, 400);
        $recentEnded = $this->exercise('Recent ended', 10, 400);
        $recentPrepared = $this->exercise('Recent prepared', null, 10);
        $guest = User::factory()->create();
        $guest->forceFill(['guest_exercise_id' => $oldEnded->id])->save();
        $oldEnded->participants()->forceCreate(['user_id' => $guest->id, 'role' => 'participant']);

        $this->artisan('iron-table:retention')->expectsOutputToContain('2 exercises older than 365 days deleted')->assertSuccessful();

        $this->assertSame(['Recent ended', 'Recent prepared'], Exercise::query()->orderBy('id')->pluck('title')->all());
        $this->assertModelMissing($guest);
        Storage::assertMissing("exercise-images/{$oldEnded->id}/door.png");
        Storage::assertMissing("reports/{$oldPrepared->id}/v1/aar.pdf");
        Storage::assertExists("reports/{$recentEnded->id}/v1/aar.pdf");
        Storage::assertExists("exercise-images/{$recentPrepared->id}/door.png");

        $this->assertSame(2, AuditEvent::query()->where('action', 'retention.delete')->count());
        $this->assertSame(1, AuditEvent::query()->where('action', 'test.before')->count());
        $this->assertNull(app(AuditLog::class)->verify()['broken']);
    }
}
