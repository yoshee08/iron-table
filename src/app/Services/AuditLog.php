<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The append-only audit log with a hash chain (spec SEC-06 and section 10).
 *
 * The hash of each event covers its fields and the hash of the event before it.
 * If somebody changes or deletes an event in the database, verify() finds the
 * first event where the chain breaks.
 */
class AuditLog
{
    public const FIRST_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * @param  array<string, mixed>  $data  never put passwords, plan text or session content here
     */
    public function record(string $action, array $data = [], ?User $actor = null, ?string $actorName = null): AuditEvent
    {
        return DB::transaction(function () use ($action, $data, $actor, $actorName) {
            // One writer at a time, so that two events cannot use the same previous hash.
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(2026112600)');
            }

            $event = new AuditEvent;
            $event->forceFill([
                'created_at' => Carbon::now('UTC')->format('Y-m-d\TH:i:s.u\Z'),
                'actor_id' => $actor?->getKey(),
                'actor' => mb_substr($actor ? $actor->email : ($actorName ?? 'system'), 0, 255),
                'action' => mb_substr($action, 0, 100),
                'data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'ip' => app()->runningInConsole() ? null : request()->ip(),
                'previous_hash' => AuditEvent::query()->orderByDesc('id')->value('hash') ?? self::FIRST_HASH,
            ]);
            $event->hash = self::hash($event);
            $event->save();

            return $event;
        });
    }

    /**
     * Checks the full chain. Returns the number of events and the ID of the first
     * event that does not agree with the chain, or null if all events agree.
     *
     * @return array{count: int, broken: int|null}
     */
    public function verify(): array
    {
        $previous = self::FIRST_HASH;
        $count = 0;
        foreach (AuditEvent::query()->lazyById(500) as $event) {
            $count++;
            if ($event->previous_hash !== $previous || ! hash_equals(self::hash($event), $event->hash)) {
                return ['count' => $count, 'broken' => $event->id];
            }
            $previous = $event->hash;
        }

        return ['count' => $count, 'broken' => null];
    }

    private static function hash(AuditEvent $event): string
    {
        return hash('sha256', implode("\n", [
            $event->previous_hash,
            $event->created_at,
            (string) $event->actor_id,
            $event->actor,
            $event->action,
            $event->data,
            (string) $event->ip,
        ]));
    }
}
