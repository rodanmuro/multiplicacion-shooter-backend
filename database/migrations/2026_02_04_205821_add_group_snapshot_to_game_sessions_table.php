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
        Schema::table('game_sessions', function (Blueprint $table) {
            // Agregar columna group_snapshot después de user_id
            $table->string('group_snapshot', 50)->nullable()->after('user_id');

            // Agregar índice para búsquedas rápidas por grupo
            $table->index('group_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            // Eliminar índice
            $table->dropIndex(['group_snapshot']);

            // Eliminar columna
            $table->dropColumn('group_snapshot');
        });
    }
};
