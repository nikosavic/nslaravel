<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Canonical game records, shared by every user and every platform.
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('igdb_id')->nullable()->unique();
            $table->string('title')->index();
            $table->string('cover_url', 500)->nullable();
            $table->date('release_date')->nullable();
            $table->text('summary')->nullable();
            $table->json('genres')->nullable();
            // Set once an IGDB lookup has been attempted (igdb_id stays null if nothing matched).
            $table->timestamp('enriched_at')->nullable();
            $table->timestamps();
        });

        // Maps a provider's own id (Steam appid, Xbox titleId, PSN titleId...) to a canonical game.
        Schema::create('game_external_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('external_id', 64);
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_external_ids');
        Schema::dropIfExists('games');
    }
};
