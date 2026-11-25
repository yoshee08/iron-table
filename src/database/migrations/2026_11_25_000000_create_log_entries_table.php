<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The logs of a session (spec section 8.2), the parking lot and moderator notes.
        Schema::create('log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->json('fields');
            $table->unsignedInteger('sim_day');
            $table->string('sim_time', 5);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['exercise_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_entries');
    }
};
