<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One audit event (spec SEC-06). Events are append-only: write them only with
 * App\Services\AuditLog, which also makes the hash chain.
 */
class AuditEvent extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        // The database trigger also stops this (PostgreSQL).
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return json_decode($this->data, true) ?: [];
    }
}
