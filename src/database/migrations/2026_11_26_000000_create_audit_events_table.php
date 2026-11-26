<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The audit log (spec SEC-06 and section 10). Append-only: a trigger stops updates,
        // deletes and TRUNCATE. Each event has the hash of the event before it (a hash chain).
        // There is no foreign key to users, because a deleted user must not change old events.
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('created_at', 32);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor', 255);
            $table->string('action', 100);
            $table->text('data');
            $table->string('ip', 45)->nullable();
            $table->string('previous_hash', 64);
            $table->string('hash', 64);
            $table->index('action');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION audit_events_append_only() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_events is append-only';
                END;
                $$;
                CREATE TRIGGER audit_events_no_change BEFORE UPDATE OR DELETE ON audit_events
                    FOR EACH ROW EXECUTE FUNCTION audit_events_append_only();
                CREATE TRIGGER audit_events_no_truncate BEFORE TRUNCATE ON audit_events
                    FOR EACH STATEMENT EXECUTE FUNCTION audit_events_append_only();
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS audit_events_append_only()');
        }
    }
};
