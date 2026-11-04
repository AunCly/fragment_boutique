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
        Schema::create('fragment_ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('fragment_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('version_id')->nullable();
            $table->string('kind');
            $table->string('status');
            $table->json('parameters');
            $table->json('suggestions')->nullable();
            $table->json('warnings')->nullable();
            $table->text('error')->nullable();
            $table->json('usage')->nullable();
            $table->decimal('cost_usd', 10, 5)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fragment_ai_runs');
    }
};
