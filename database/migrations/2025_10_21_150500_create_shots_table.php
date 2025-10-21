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
        Schema::create('shots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions')->onDelete('cascade');
            // Timestamp con precisión de milisegundos
            $table->timestamp('shot_at', 3);
            // Coordenadas del disparo
            $table->decimal('coordinate_x', 8, 2);
            $table->decimal('coordinate_y', 8, 2);
            // Factores de multiplicación y valores
            $table->integer('factor_1');
            $table->integer('factor_2');
            $table->integer('correct_answer');
            $table->integer('card_value');
            $table->boolean('is_correct');
            $table->timestamps();

            // Índices para consultas frecuentes
            $table->index('game_session_id');
            $table->index('is_correct');
            $table->index(['factor_1', 'factor_2']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shots');
    }
};

