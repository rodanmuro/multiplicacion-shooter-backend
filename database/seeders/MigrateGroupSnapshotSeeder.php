<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateGroupSnapshotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Migra el grupo actual de cada usuario a group_snapshot en sus sesiones históricas.
     * IMPORTANTE: Ejecutar ANTES de actualizar los grupos de usuarios para 2026.
     */
    public function run(): void
    {
        $this->command->info('Iniciando migración de group_snapshot...');

        // Actualizar sesiones que aún no tienen group_snapshot
        $updated = DB::statement("
            UPDATE game_sessions gs
            JOIN users u ON gs.user_id = u.id
            SET gs.group_snapshot = u.group
            WHERE gs.group_snapshot IS NULL
            AND u.group IS NOT NULL
        ");

        $count = DB::table('game_sessions')
            ->whereNotNull('group_snapshot')
            ->count();

        $this->command->info("✓ Migración completada: {$count} sesiones actualizadas con group_snapshot");
    }
}
