<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Spec SEC-05: a guest joins with the session code of one exercise. The code
        // is empty when guest access is off and when the session has ended.
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('guest_code', 12)->nullable()->unique();
        });

        // A guest gets a temporary account for that one exercise only. It is
        // deleted with the exercise.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('guest_exercise_id')->nullable()->constrained('exercises')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guest_exercise_id');
        });
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropUnique(['guest_code']);
            $table->dropColumn('guest_code');
        });
    }
};
