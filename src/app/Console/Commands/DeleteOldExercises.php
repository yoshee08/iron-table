<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Console\Commands;

use App\Models\Exercise;
use App\Services\AuditLog;
use Illuminate\Console\Command;

/**
 * Retention (spec SEC-07): deletes the exercises that are older than RETENTION_DAYS,
 * with their records, guest accounts, pictures and report files. The scheduler runs
 * it each day. The audit log keeps its events.
 */
class DeleteOldExercises extends Command
{
    protected $signature = 'iron-table:retention';

    protected $description = 'Delete the exercises that are older than the retention period (RETENTION_DAYS)';

    public function handle(AuditLog $audit): int
    {
        $days = (int) config('iron-table.retention_days');
        if ($days <= 0) {
            $this->info('Retention: RETENTION_DAYS is not set. No exercise is deleted.');

            return self::SUCCESS;
        }

        $limit = now()->subDays($days);
        $old = Exercise::query()
            ->where(fn ($query) => $query->whereNotNull('ended_at')->where('ended_at', '<', $limit))
            ->orWhere(fn ($query) => $query->whereNull('ended_at')->where('updated_at', '<', $limit))
            ->get();

        foreach ($old as $exercise) {
            $exercise->purge();
            $audit->record('retention.delete', ['exercise' => $exercise->id, 'days' => $days], null, 'retention');
        }
        $this->info("Retention: {$old->count()} exercises older than $days days deleted.");

        return self::SUCCESS;
    }
}
