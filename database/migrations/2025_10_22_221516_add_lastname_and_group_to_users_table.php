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
        Schema::table('users', function (Blueprint $table) {
            // Agregar columna lastname después de name
            $table->string('lastname')->nullable()->after('name');

            // Agregar columna group después de profile
            $table->string('group')->nullable()->after('profile');

            // Agregar índice en group para búsquedas más rápidas
            $table->index('group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Eliminar índice de group
            $table->dropIndex(['group']);

            // Eliminar columnas
            $table->dropColumn(['lastname', 'group']);
        });
    }
};
