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
        Schema::create('palette_wood_texture', function (Blueprint $table) {
            $table->foreignId('palette_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wood_texture_id')->constrained()->cascadeOnDelete();
            $table->primary(['palette_id', 'wood_texture_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('palette_wood_texture');
    }
};
