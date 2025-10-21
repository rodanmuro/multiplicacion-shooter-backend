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
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->integer('final_score')->default(0);
            $table->integer('max_level_reached')->default(1);
            $table->integer('duration_seconds')->default(0);
            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['user_id', 'started_at']);
            $table->index('finished_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
