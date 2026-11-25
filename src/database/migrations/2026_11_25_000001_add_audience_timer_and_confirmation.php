<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backlog B9 (who sees an inject) and B10 (inject timer, in real minutes).
        Schema::table('inject_releases', function (Blueprint $table) {
            $table->json('audience')->nullable();
            $table->unsignedInteger('timer_minutes')->nullable();
        });

        // Spec section 4.2: each participant confirms the plan role at the briefing.
        Schema::table('participants', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('participants', fn (Blueprint $table) => $table->dropColumn('confirmed_at'));
        Schema::table('inject_releases', fn (Blueprint $table) => $table->dropColumn(['audience', 'timer_minutes']));
    }
};
