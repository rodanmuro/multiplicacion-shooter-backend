# Bitácora Backend - INCREMENTO 9: Group Snapshot - Historial de Grupos de Estudiantes
**Fecha:** 4 de febrero de 2026
**Responsable:** Claude Code
**Incremento:** 9 - Sistema de Snapshot de Grupos para Seguimiento Histórico

---

## Resumen

Implementación de un sistema de snapshot de grupos que permite mantener el historial de a qué grupo pertenecía un estudiante cuando jugó cada sesión. Esto resuelve el problema de seguimiento de progreso cuando los estudiantes cambian de grupo anualmente (ej: 10A2025 → 11A2026) o por cambios de sección.

**Problema resuelto:** Los estudiantes cambian de grupo cada año lectivo, pero necesitamos saber en qué grupo estaban cuando jugaron cada sesión histórica para análisis y reportes precisos.

**Solución:** Agregar columna `group_snapshot` a la tabla `game_sessions` que captura el grupo actual del estudiante al momento de iniciar cada sesión.

---

## Cambios Realizados

### 1. Nueva Migración - Agregar Columna group_snapshot

**Archivo:** `database/migrations/2026_02_04_205821_add_group_snapshot_to_game_sessions_table.php`

**Cambios en esquema:**

```php
public function up(): void
{
    Schema::table('game_sessions', function (Blueprint $table) {
        // Agregar columna group_snapshot después de user_id
        $table->string('group_snapshot', 50)->nullable()->after('user_id');

        // Agregar índice para búsquedas rápidas por grupo
        $table->index('group_snapshot');
    });
}
```

**Características:**
- ✅ Columna `group_snapshot` tipo VARCHAR(50)
- ✅ Nullable para sesiones históricas antes de esta actualización
- ✅ Posicionada después de `user_id` para agrupación lógica
- ✅ Índice agregado para optimizar queries por grupo

**Rollback:**
```php
public function down(): void
{
    Schema::table('game_sessions', function (Blueprint $table) {
        // Eliminar índice
        $table->dropIndex(['group_snapshot']);

        // Eliminar columna
        $table->dropColumn('group_snapshot');
    });
}
```

---

### 2. Seeder de Migración de Datos Históricos

**Archivo:** `database/seeders/MigrateGroupSnapshotSeeder.php`

**Propósito:** Migrar el grupo actual de cada usuario a sus sesiones históricas existentes.

**IMPORTANTE:** Este seeder debe ejecutarse **ANTES** de actualizar los grupos de usuarios para 2026.

```php
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
```

**Lógica:**
1. JOIN entre `game_sessions` y `users` por `user_id`
2. Copia el campo `u.group` a `gs.group_snapshot`
3. Solo actualiza sesiones donde `group_snapshot` es NULL
4. Solo copia si el usuario tiene grupo definido

**Ventajas:**
- ✅ Idempotente (puede ejecutarse múltiples veces sin duplicar)
- ✅ Eficiente (una sola query UPDATE con JOIN)
- ✅ Preserva datos existentes (condición WHERE IS NULL)

---

### 3. Modelo GameSession Actualizado

**Archivo:** `app/Models/GameSession.php`

**Líneas modificadas:** 23-33

**Cambio en $fillable:**

```php
protected $fillable = [
    'user_id',
    'group_snapshot',  // ← AGREGADO
    'started_at',
    'finished_at',
    'final_score',
    'max_level_reached',
    'duration_seconds',
    'canvas_width',
    'canvas_height',
];
```

**Efecto:** Permite asignación masiva de `group_snapshot` al crear o actualizar sesiones mediante `GameSession::create()` o `GameSession::update()`.

---

### 4. GameSessionController - Captura Automática de Grupo

**Archivo:** `app/Http/Controllers/GameSessionController.php`

**Líneas modificadas:** 82-91

**Método:** `store()` - Crear nueva sesión de juego

**Cambio:**

```php
$session = GameSession::create([
    'user_id' => $user->id,
    'group_snapshot' => $user->group,  // ← AGREGADO: Snapshot del grupo actual
    'started_at' => $validated['started_at'],
    'final_score' => 0,
    'max_level_reached' => 1,
    'duration_seconds' => 0,
    'canvas_width' => $validated['canvas_width'],
    'canvas_height' => $validated['canvas_height']
]);
```

**Comportamiento:**
- Al crear una nueva sesión, captura automáticamente el grupo actual del usuario autenticado
- Si el usuario no tiene grupo (`null`), se almacena `null` en `group_snapshot`
- Cada sesión guarda una "fotografía" del grupo en ese momento exacto

**Ejemplo de evolución en el tiempo:**

| Fecha | Grupo Usuario | Sesión Creada | group_snapshot |
|-------|--------------|---------------|----------------|
| 2025-03-15 | 10A2025 | Sesión #123 | 10A2025 |
| 2025-06-20 | 10A2025 | Sesión #456 | 10A2025 |
| **2026-02-01** | **11A2026** ← Usuario actualizado | | |
| 2026-02-10 | 11A2026 | Sesión #789 | 11A2026 |

Resultado: Las sesiones #123 y #456 mantienen `10A2025`, la sesión #789 tiene `11A2026`.

---

### 5. AdminController - Retornar group_snapshot en Sesiones

**Archivo:** `app/Http/Controllers/AdminController.php`

**Líneas modificadas:** 233-250

**Método:** `getUserSessions()` - Obtener sesiones de un usuario

**Cambio en respuesta:**

```php
return [
    'row_number' => $rowNumber,
    'id' => $session->id,
    'user_id' => $session->user_id,
    'group_snapshot' => $session->group_snapshot,  // ← AGREGADO
    'started_at' => $session->started_at->format('Y-m-d H:i:s'),
    'finished_at' => $session->finished_at?->format('Y-m-d H:i:s'),
    'final_score' => $session->final_score,
    'max_level_reached' => $session->max_level_reached,
    'duration_seconds' => $session->duration_seconds,
    'shots_count' => $session->shots_count
];
```

**Efecto:** El frontend ahora recibe el grupo histórico de cada sesión, permitiendo:
- Mostrar en qué grupo estaba el estudiante cuando jugó
- Filtrar sesiones por grupo histórico
- Generar reportes por grupos pasados

---

## Script de Despliegue en Producción

**Archivo:** `migrate_group_snapshot.php`

**Ubicación en producción:** `/home/heverdar/public_html/multiplicacion/api/migrate_group_snapshot.php`

**Propósito:** Ejecutar la migración en hosting compartido sin acceso SSH.

### Proceso de Ejecución

El script realiza los siguientes pasos automáticamente:

```php
// 1. Ejecutar migration específica
Artisan::call('migrate', [
    '--force' => true,
    '--path' => 'database/migrations/2026_02_04_205821_add_group_snapshot_to_game_sessions_table.php'
]);

// 2. Ejecutar seeder para migrar datos históricos
Artisan::call('db:seed', [
    '--class' => 'MigrateGroupSnapshotSeeder',
    '--force' => true
]);

// 3. Verificar resultados
$totalSessions = DB::table('game_sessions')->count();
$sessionsWithSnapshot = DB::table('game_sessions')
    ->whereNotNull('group_snapshot')
    ->count();

// 4. Limpiar cache
Artisan::call('config:clear');
Artisan::call('cache:clear');
Artisan::call('route:clear');
```

### Resultado de Ejecución en Producción

✅ **Migration ejecutada correctamente**
✅ **Seeder ejecutado correctamente**
✅ **Datos históricos migrados**
✅ **Cache limpiado**
✅ **Archivo eliminado después de ejecución**

**Verificación:**
- Total de sesiones en BD: consultado
- Sesiones con group_snapshot: contadas
- Ejemplos de sesiones migradas: mostrados

---

## Comportamiento de los Endpoints

### Endpoint: POST /api/sessions

**Cambio:** Ahora captura `group_snapshot` automáticamente.

#### Request
```json
POST /api/sessions
Authorization: Bearer <token>
Content-Type: application/json

{
  "started_at": "2026-02-04 15:30:00",
  "canvas_width": 1200,
  "canvas_height": 800
}
```

#### Response
```json
{
  "message": "Sesión creada exitosamente",
  "session": {
    "id": 789,
    "user_id": 42,
    "group_snapshot": "11A2026",  // ← NUEVO: Capturado automáticamente
    "started_at": "2026-02-04T15:30:00.000000Z",
    "finished_at": null,
    "final_score": 0,
    "max_level_reached": 1,
    "duration_seconds": 0
  }
}
```

**Comportamiento:**
- El campo `group_snapshot` se llena automáticamente con `$user->group`
- No requiere envío en el request
- Transparente para el frontend

---

### Endpoint: GET /api/admin/users/{userId}/sessions

**Cambio:** Ahora retorna `group_snapshot` en cada sesión.

#### Request
```bash
GET /api/admin/users/42/sessions?page=1&per_page=20
Authorization: Bearer <admin_token>
```

#### Response
```json
{
  "current_page": 1,
  "data": [
    {
      "row_number": 1,
      "id": 789,
      "user_id": 42,
      "group_snapshot": "11A2026",  // ← NUEVO: Grupo cuando jugó esta sesión
      "started_at": "2026-02-04 15:30:00",
      "finished_at": "2026-02-04 15:35:00",
      "final_score": 850,
      "max_level_reached": 8,
      "duration_seconds": 300,
      "shots_count": 45
    },
    {
      "row_number": 2,
      "id": 456,
      "user_id": 42,
      "group_snapshot": "10A2025",  // ← Grupo histórico diferente
      "started_at": "2025-06-20 10:15:00",
      "finished_at": "2025-06-20 10:20:00",
      "final_score": 720,
      "max_level_reached": 6,
      "duration_seconds": 300,
      "shots_count": 38
    }
  ],
  "total": 15,
  "per_page": 20
}
```

**Ventajas:**
- ✅ Frontend puede mostrar grupo histórico en cada sesión
- ✅ Permite filtrar sesiones por grupo pasado
- ✅ Útil para reportes de rendimiento por grupo/año

---

## Casos de Uso

### Caso 1: Cambio de Año Lectivo

**Situación:**
- Estudiante María está en **10A2025** durante 2025
- Jugó 50 sesiones en 2025
- En febrero 2026, María pasa a **11A2026**

**Sin group_snapshot:**
- Las 50 sesiones del 2025 mostrarían grupo "11A2026" ❌
- Imposible saber en qué grupo estaba cuando las jugó
- Reportes de 2025 incorrectos

**Con group_snapshot:**
- Las 50 sesiones de 2025 mantienen "10A2025" ✅
- Nuevas sesiones de 2026 tendrán "11A2026"
- Reportes precisos por año/grupo

### Caso 2: Cambio de Sección

**Situación:**
- Estudiante Juan está en **5B2026**
- Jugó 20 sesiones
- Por rendimiento académico, se cambia a **5A2026**

**Con group_snapshot:**
- Las 20 sesiones históricas mantienen "5B2026"
- Nuevas sesiones tendrán "5A2026"
- Historial completo y preciso del estudiante

### Caso 3: Reportes por Grupo Histórico

**Query posible:**
```sql
-- Promedio de puntajes del grupo 10A2025 durante el año 2025
SELECT AVG(final_score) as avg_score, COUNT(*) as sessions
FROM game_sessions
WHERE group_snapshot = '10A2025'
  AND started_at BETWEEN '2025-01-01' AND '2025-12-31';
```

**Ventaja:** Análisis preciso del rendimiento de grupos que ya no existen en la tabla `users`.

---

## Proceso de Despliegue Ejecutado

### Fase 1 - Preparación (Local)

✅ Creada migration `2026_02_04_205821_add_group_snapshot_to_game_sessions_table.php`
✅ Creado seeder `MigrateGroupSnapshotSeeder.php`
✅ Actualizado modelo `GameSession.php`
✅ Actualizado controller `GameSessionController.php`
✅ Actualizado controller `AdminController.php`
✅ Testing local ejecutado exitosamente

### Fase 2 - Despliegue (Producción)

**Archivos subidos vía FTP:**

1. ✅ `app/Http/Controllers/AdminController.php`
   - Ruta: `/home/heverdar/multiplication-shooter/app/Http/Controllers/`

2. ✅ `app/Http/Controllers/GameSessionController.php`
   - Ruta: `/home/heverdar/multiplication-shooter/app/Http/Controllers/`

3. ✅ `app/Models/GameSession.php`
   - Ruta: `/home/heverdar/multiplication-shooter/app/Models/`

4. ✅ `database/migrations/2026_02_04_205821_add_group_snapshot_to_game_sessions_table.php`
   - Ruta: `/home/heverdar/multiplication-shooter/database/migrations/`

5. ✅ `database/seeders/MigrateGroupSnapshotSeeder.php`
   - Ruta: `/home/heverdar/multiplication-shooter/database/seeders/`

6. ✅ `migrate_group_snapshot.php`
   - Ruta: `/home/heverdar/public_html/multiplicacion/api/`

### Fase 3 - Ejecución de Migración

**URL ejecutada:** `https://voyeducando.com/multiplicacion/api/migrate_group_snapshot.php`

**Resultado:**
- ✅ Migration ejecutada correctamente
- ✅ Columna `group_snapshot` agregada a tabla `game_sessions`
- ✅ Índice creado exitosamente
- ✅ Seeder ejecutado correctamente
- ✅ Datos históricos migrados
- ✅ Cache de Laravel limpiado
- ✅ Archivo `migrate_group_snapshot.php` eliminado por seguridad

---

## Archivos Modificados

### Backend

1. ✅ `database/migrations/2026_02_04_205821_add_group_snapshot_to_game_sessions_table.php`
   - Archivo nuevo
   - Agrega columna `group_snapshot` e índice

2. ✅ `database/seeders/MigrateGroupSnapshotSeeder.php`
   - Archivo nuevo
   - Migra datos históricos

3. ✅ `app/Models/GameSession.php`
   - Líneas 23-33
   - Agregado `group_snapshot` a `$fillable`

4. ✅ `app/Http/Controllers/GameSessionController.php`
   - Líneas 82-91
   - Método `store()`: captura `group_snapshot` al crear sesión

5. ✅ `app/Http/Controllers/AdminController.php`
   - Líneas 233-250
   - Método `getUserSessions()`: retorna `group_snapshot` en respuesta

### Despliegue

6. ✅ `migrate_group_snapshot.php`
   - Script temporal para ejecutar migración en producción
   - **Eliminado después de ejecución**

---

## Esquema de Base de Datos Actualizado

### Tabla: game_sessions

```sql
CREATE TABLE game_sessions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    group_snapshot VARCHAR(50) NULL,           -- ← NUEVO
    started_at TIMESTAMP NOT NULL,
    finished_at TIMESTAMP NULL,
    final_score INT DEFAULT 0,
    max_level_reached INT DEFAULT 1,
    duration_seconds INT DEFAULT 0,
    canvas_width INT NULL,
    canvas_height INT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_user_started (user_id, started_at),
    INDEX idx_finished (finished_at),
    INDEX idx_group_snapshot (group_snapshot),  -- ← NUEVO

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Cambios:**
- ✅ Nueva columna: `group_snapshot VARCHAR(50) NULL`
- ✅ Nuevo índice: `idx_group_snapshot` para búsquedas por grupo

---

## Testing y Verificación

### Pruebas Locales (Antes de Despliegue)

✅ **1. Ejecutar migration:**
```bash
php artisan migrate --path=database/migrations/2026_02_04_205821_add_group_snapshot_to_game_sessions_table.php
```
Resultado: Columna agregada exitosamente

✅ **2. Ejecutar seeder:**
```bash
php artisan db:seed --class=MigrateGroupSnapshotSeeder
```
Resultado: Datos históricos migrados

✅ **3. Verificar columna en BD:**
```sql
DESCRIBE game_sessions;
```
Resultado: Columna `group_snapshot` presente

✅ **4. Crear nueva sesión:**
- Usuario con grupo "11A2026"
- Sesión creada con `group_snapshot = "11A2026"`

✅ **5. Consultar sesiones vía API:**
- Endpoint `/api/admin/users/{id}/sessions`
- Respuesta incluye `group_snapshot` en cada sesión

### Pruebas en Producción (Después de Despliegue)

✅ **1. Ejecución de script de migración:**
- URL: `https://voyeducando.com/multiplicacion/api/migrate_group_snapshot.php`
- Resultado: Migración completada exitosamente

✅ **2. Verificación de columna:**
- Columna `group_snapshot` existente en producción
- Índice creado correctamente

✅ **3. Datos históricos migrados:**
- Sesiones existentes tienen `group_snapshot` poblado
- Valores coinciden con grupos actuales de usuarios

✅ **4. Nuevas sesiones:**
- Al jugar, nuevas sesiones capturan `group_snapshot` automáticamente

---

## Conclusión

✅ **Columna group_snapshot agregada exitosamente**
✅ **Datos históricos migrados preservando contexto**
✅ **Captura automática en nuevas sesiones**
✅ **API actualizada para retornar grupo histórico**
✅ **Despliegue en producción completado**
✅ **Sistema listo para actualización de grupos 2026**

El sistema ahora puede:
- Mantener historial preciso de grupos de estudiantes
- Generar reportes por grupos históricos
- Analizar rendimiento de grupos que ya no existen
- Seguir progreso de estudiantes a través de los años

**Próximos pasos:**
1. Actualizar grupos de estudiantes para 2026 (10A2025 → 11A2026, etc.)
2. Las nuevas sesiones capturarán automáticamente los nuevos grupos
3. Las sesiones históricas mantendrán los grupos del 2025
4. Implementar filtros por grupo histórico en panel admin (pendiente)

---

## Referencias

- **Migration:** `database/migrations/2026_02_04_205821_add_group_snapshot_to_game_sessions_table.php`
- **Seeder:** `database/seeders/MigrateGroupSnapshotSeeder.php`
- **Modelo:** `app/Models/GameSession.php` (líneas 23-33)
- **Controller:** `app/Http/Controllers/GameSessionController.php` (líneas 82-91)
- **Admin Controller:** `app/Http/Controllers/AdminController.php` (líneas 233-250)
- **Laravel Docs:** [Migrations - Adding Columns](https://laravel.com/docs/11.x/migrations#columns)
- **Laravel Docs:** [Database Seeding](https://laravel.com/docs/11.x/seeding)
- **Pattern:** Snapshot Pattern para datos históricos
