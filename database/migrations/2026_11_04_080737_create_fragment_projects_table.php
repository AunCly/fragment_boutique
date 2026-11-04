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
        Schema::create('fragment_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_path');
            $table->string('thumbnail_path');
            $table->unsignedInteger('source_width');
            $table->unsignedInteger('source_height');
            $table->unsignedInteger('crop_x');
            $table->unsignedInteger('crop_y');
            $table->unsignedInteger('crop_width');
            $table->unsignedInteger('crop_height');
            $table->foreignId('format_id')->constrained()->restrictOnDelete();
            $table->foreignId('palette_id')->constrained()->restrictOnDelete();
            $table->foreignId('background_id')->constrained('wood_textures')->restrictOnDelete();
            $table->text('hint')->nullable();
            $table->unsignedBigInteger('selected_version_id')->nullable();
            $table->decimal('cost_usd', 10, 5)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fragment_projects');
    }
};
