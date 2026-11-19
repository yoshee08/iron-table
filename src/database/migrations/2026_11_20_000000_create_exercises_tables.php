<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One exercise has one live session, so the session fields are here (spec section 10).
        // "scenario" is the checked scenario with all placeholders replaced, frozen when the exercise is made.
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('scenario_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scenario_version', 20);
            $table->foreignId('ir_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->json('scenario');
            $table->string('status', 20)->default('prepared');
            $table->unsignedInteger('sim_day')->default(1);
            $table->string('sim_time', 5)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('plan_role')->nullable();
            $table->timestamps();
            $table->unique(['exercise_id', 'user_id']);
        });

        // The inject exactly as participants saw it (spec section 10, rules).
        Schema::create('inject_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('inject_key', 100);
            $table->string('title');
            $table->text('body');
            $table->json('artifacts');
            $table->json('questions');
            $table->unsignedInteger('sim_day');
            $table->string('sim_time', 5);
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inject_release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_role')->nullable();
            $table->text('body');
            $table->boolean('no_coverage')->default(false);
            $table->unsignedInteger('sim_day');
            $table->string('sim_time', 5);
            $table->timestamps();
        });

        // Citations: a response cites plan sections (spec section 5.3).
        Schema::create('citations', function (Blueprint $table) {
            $table->foreignId('response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_section_id')->constrained()->cascadeOnDelete();
            $table->primary(['response_id', 'plan_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citations');
        Schema::dropIfExists('responses');
        Schema::dropIfExists('inject_releases');
        Schema::dropIfExists('participants');
        Schema::dropIfExists('exercises');
    }
};
