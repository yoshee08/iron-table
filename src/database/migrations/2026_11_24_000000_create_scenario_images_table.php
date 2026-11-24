<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One AI picture request for a picture artifact. The moderator chooses a finished picture.
        Schema::create('scenario_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained()->cascadeOnDelete();
            $table->string('inject_key', 100);
            $table->unsignedInteger('artifact');
            $table->text('prompt');
            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();
            $table->string('file')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_images');
    }
};
