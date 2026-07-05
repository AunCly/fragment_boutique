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
        Schema::table('wood_textures', function (Blueprint $table) {
            $table->renameColumn('texture_url', 'texture_path');
        });

        Schema::table('wood_textures', function (Blueprint $table) {
            $table->string('texture_path', 500)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wood_textures', function (Blueprint $table) {
            $table->renameColumn('texture_path', 'texture_url');
        });
    }
};
