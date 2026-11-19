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
        // Each upload is one version of the organization's IR plan (spec section 5.1).
        Schema::create('ir_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('title');
            $table->string('file_name');
            $table->string('file_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plan_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ir_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('number')->nullable();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
            $table->index(['ir_plan_id', 'position']);
        });

        // PostgreSQL full-text search (spec section 5.3).
        DB::statement("ALTER TABLE plan_sections ADD COLUMN search tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(number, '') || ' ' || title || ' ' || body)) STORED");
        DB::statement('CREATE INDEX plan_sections_search_index ON plan_sections USING GIN (search)');
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_sections');
        Schema::dropIfExists('ir_plans');
    }
};
