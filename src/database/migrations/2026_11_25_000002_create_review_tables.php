<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Spec section 8.3: one mark for each rubric item. A changed score needs a reason.
        Schema::create('rubric_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('inject_key', 100);
            $table->unsignedInteger('item');
            $table->string('result', 10);
            $table->unsignedInteger('points');
            $table->text('reason')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['exercise_id', 'inject_key', 'item']);
        });

        // Backlog B4: each objective gets an HSEEP rating with a justification.
        Schema::create('objective_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('objective');
            $table->string('rating', 20);
            $table->text('justification');
            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['exercise_id', 'objective']);
        });

        // Spec sections 4.4 and 8.4, backlog B5: strengths, weaknesses and plan gaps,
        // each with an optional corrective action.
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('text');
            $table->string('control')->nullable();
            $table->text('action')->nullable();
            $table->string('owner')->nullable();
            $table->date('due_date')->nullable();
            $table->string('documents')->nullable();
            $table->string('status', 10)->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
        Schema::dropIfExists('objective_ratings');
        Schema::dropIfExists('rubric_marks');
    }
};
