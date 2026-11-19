<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Organization profile (spec section 5.2, typed by a moderator): YAML, used as {{org.*}} placeholders.
        Schema::table('ir_plans', function (Blueprint $table) {
            $table->text('profile')->nullable();
        });

        // Plan concept of a section, for example severity_table (spec section 6.3).
        Schema::table('plan_sections', function (Blueprint $table) {
            $table->string('concept')->nullable();
        });

        // One scenario version. The YAML source is kept exactly as imported (spec section 6).
        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100);
            $table->string('version', 20);
            $table->string('title');
            $table->text('source');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['slug', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenarios');
        Schema::table('plan_sections', fn (Blueprint $table) => $table->dropColumn('concept'));
        Schema::table('ir_plans', fn (Blueprint $table) => $table->dropColumn('profile'));
    }
};
