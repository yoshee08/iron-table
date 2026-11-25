<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The after-action report (spec sections 4.5 and 8.4, backlog B6 and B8). A draft is
        // edited; Final freezes the content and the evidence files of that version.
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 10)->default('draft');
            $table->string('tlp', 20)->default('TLP:AMBER');
            $table->json('content');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['exercise_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
