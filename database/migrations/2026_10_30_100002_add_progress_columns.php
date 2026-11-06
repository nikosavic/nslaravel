<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // IGDB "time to beat", in minutes. Null until IGDB has been asked (ttb_checked_at) or if it has no data.
            $table->unsignedInteger('ttb_main_minutes')->nullable();
            $table->unsignedInteger('ttb_complete_minutes')->nullable();
            $table->timestamp('ttb_checked_at')->nullable();
        });

        Schema::table('library_entries', function (Blueprint $table) {
            // Steam achievements / PSN trophies. Null when the platform has none or has not been read yet.
            $table->unsignedSmallInteger('achievements_unlocked')->nullable();
            $table->unsignedSmallInteger('achievements_total')->nullable();
            $table->json('trophies')->nullable(); // {"bronze":n,"silver":n,"gold":n,"platinum":n} (earned counts)
            $table->timestamp('achievements_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('library_entries', function (Blueprint $table) {
            $table->dropColumn(['achievements_unlocked', 'achievements_total', 'trophies', 'achievements_synced_at']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['ttb_main_minutes', 'ttb_complete_minutes', 'ttb_checked_at']);
        });
    }
};
