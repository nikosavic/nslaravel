<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row = "this user has this game on this platform".
        // Status, rating and notes belong to the user; providers only feed playtime and ownership.
        Schema::create('library_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->restrictOnDelete();
            $table->foreignId('linked_account_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 16)->default('backlog');
            $table->unsignedInteger('playtime_minutes')->default(0);
            $table->timestamp('last_played_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1-10
            $table->text('notes')->nullable();
            $table->string('source', 16)->default('manual');   // manual | steam | xbox | playstation
            $table->timestamps();

            $table->unique(['user_id', 'game_id', 'platform_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_entries');
    }
};
