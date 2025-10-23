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
        // Verificar si el índice único existe antes de intentar borrarlo
        if ($this->indexExists('users', 'users_google_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['google_id']);
            });
        }

        // Hacer google_id nullable
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->change();
        });

        // Agregar índice en email para búsquedas en CSV upload (solo si no existe)
        // El índice puede ya existir de migraciones anteriores
        if (!$this->indexExists('users', 'users_email_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('email');
            });
        }
    }

    /**
     * Verifica si un índice existe en una tabla
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        $result = $connection->select(
            "SELECT COUNT(*) as count FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$databaseName, $table, $index]
        );

        return $result[0]->count > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Revertir cambios
            $table->string('google_id')->nullable(false)->unique()->change();
            $table->dropIndex(['email']);
        });
    }
};
