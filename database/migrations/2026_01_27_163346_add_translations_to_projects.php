<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('title_en');
            $table->string('title_tr');
            $table->text('summary_en')->nullable();
            $table->text('summary_tr')->nullable();
            $table->longText('body_en')->nullable();
            $table->longText('body_tr')->nullable();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            //
        });
    }
};
