<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linked_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);              // steam | xbox | playstation
            $table->string('external_id', 64);           // SteamID64, XUID, PSN account id...
            $table->string('display_name')->nullable();
            $table->text('credentials')->nullable();     // encrypted JSON (tokens); cast in the model
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status', 16)->nullable(); // ok | error
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'external_id']);
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('linked_account_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('running'); // running | success | failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('games_seen')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('linked_accounts');
    }
};
