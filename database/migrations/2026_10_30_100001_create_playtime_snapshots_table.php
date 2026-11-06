<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Providers only report a running total per game. To answer "how much did I play this week" we keep
        // the total each time it changes; the difference between two rows is the time played in between.
        Schema::create('playtime_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_entry_id')->constrained()->cascadeOnDelete();
            $table->date('taken_on');
            $table->unsignedInteger('minutes');
            $table->timestamps();

            $table->unique(['library_entry_id', 'taken_on']);
            $table->index('taken_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playtime_snapshots');
    }
};
