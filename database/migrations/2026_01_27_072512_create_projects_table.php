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
    Schema::create('projects', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug')->unique();
        $table->string('summary', 280)->nullable();
        $table->text('body')->nullable();
        $table->string('repo_url')->nullable();
        $table->string('live_url')->nullable();
        $table->boolean('featured')->default(false);
        $table->unsignedInteger('sort_order')->default(0);
        $table->enum('status', ['draft', 'published'])->default('draft');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
