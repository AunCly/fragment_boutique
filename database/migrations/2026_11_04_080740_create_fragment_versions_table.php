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
        Schema::create('fragment_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('fragment_projects')->cascadeOnDelete();
            $table->foreignId('ai_run_id')->nullable()->constrained('fragment_ai_runs')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('fragment_versions')->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('kind');
            $table->string('style')->nullable();
            $table->json('grid');
            $table->foreignId('background_id')->constrained('wood_textures')->restrictOnDelete();
            $table->decimal('score', 4, 1)->nullable();
            $table->json('problems');
            $table->boolean('is_preferred')->default(false);
            $table->string('raw_image_path')->nullable();
            $table->json('edits')->nullable();
            $table->json('motif')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });

        Schema::table('fragment_projects', function (Blueprint $table) {
            $table->foreign('selected_version_id')->references('id')->on('fragment_versions')->nullOnDelete();
        });

        Schema::table('fragment_ai_runs', function (Blueprint $table) {
            $table->foreign('version_id')->references('id')->on('fragment_versions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fragment_ai_runs', function (Blueprint $table) {
            $table->dropForeign(['version_id']);
        });

        Schema::table('fragment_projects', function (Blueprint $table) {
            $table->dropForeign(['selected_version_id']);
        });

        Schema::dropIfExists('fragment_versions');
    }
};
