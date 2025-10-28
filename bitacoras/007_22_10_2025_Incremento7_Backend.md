# Bitácora Backend - INCREMENTO 7: Panel de Administración
**Fecha:** 22-23 de octubre de 2025
**Responsable:** Claude Code
**Incremento:** 7 - Panel de Administración (Backend)

**NOTA:** Se saltó el INCREMENTO 6 (Estadísticas Avanzadas) por decisión del usuario. Se implementó directamente el INCREMENTO 7.

---

## Resumen

Implementación del backend para el panel de administración, permitiendo a usuarios admin listar usuarios, ver sus sesiones y cargar usuarios masivamente desde CSV. El CSV soporta columnas opcionales para pre-cargar nombres.

---

## Cambios Realizados

### 1. Middleware RequireAdmin

**Archivo:** `app/Http/Middleware/RequireAdmin.php`

- **Creado:** Middleware para validar que el usuario autenticado sea administrador
- **Funcionalidad:**
  - Verifica que exista un usuario en el request (establecido por `auth.google`)
  - Llama al método `isAdmin()` del modelo User
  - Retorna 403 si el usuario no es admin

**Registro:**
- Agregado en `bootstrap/app.php` con alias `require.admin`

### 2. Controlador de Administración

**Archivo:** `app/Http/Controllers/AdminController.php`

Tres endpoints principales:

#### a) Listar Usuarios
```php
GET /api/admin/users?page={page}
```
- Retorna lista paginada de usuarios (15 por página)
- Incluye conteo de sesiones de cada usuario (`withCount('gameSessions')`)
- Ordenado por fecha de creación descendente

#### b) Ver Sesiones de Usuario
```php
GET /api/admin/users/{userId}/sessions?page={page}
```
- Retorna sesiones de un usuario específico (10 por página)
- Incluye información del usuario
- Ordenado por fecha de inicio descendente
- Incluye disparos de cada sesión (eager loading)

#### c) Carga Masiva CSV
```php
POST /api/admin/users/upload-csv
```
- Recibe archivo CSV con columnas obligatorias: `email`, `group`
- Columnas opcionales: `name`, `lastname` (para pre-cargar nombres)
- Valida formato CSV y encabezados
- Crea usuarios nuevos o actualiza campos de existentes
- Retorna estadísticas: creados, actualizados, errores
- Usuarios creados por CSV tienen `google_id = null` hasta su primer login
- Si se proporcionan name/lastname en CSV, se guardan; si no, quedan NULL hasta el login con Google

### 3. Rutas de Administración

**Archivo:** `routes/api.php`

Agregado grupo de rutas admin con doble middleware:
```php
Route::middleware(['auth.google', 'require.admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'listUsers']);
    Route::get('/users/{userId}/sessions', [AdminController::class, 'getUserSessions']);
    Route::post('/users/upload-csv', [AdminController::class, 'uploadCsv']);
});
```

#### Endpoints de Diagnóstico

**Agregados para facilitar despliegue y troubleshooting:**

**a) Test Endpoint**
```php
GET /api/test
```
- Endpoint básico para verificar que Laravel está respondiendo
- Retorna mensaje simple y timestamp
- No requiere base de datos ni autenticación

**b) Health Check Endpoint**
```php
GET /api/health
```
- Endpoint completo de diagnóstico del sistema
- Verifica múltiples aspectos del servidor:
  - **Laravel:** Framework funcionando
  - **PHP Version:** Versión de PHP instalada
  - **Database:** Conexión a base de datos
  - **Storage writable:** Permisos de escritura en `storage/logs`
  - **Cache writable:** Permisos de escritura en `storage/framework/cache`
  - **ENV loaded:** Archivo .env cargado correctamente
  - **APP_KEY set:** Key de aplicación configurada
  - **Database name:** Nombre de la base de datos conectada
  - **Tables:** Verifica existencia de tablas principales (users, game_sessions, shots)
- Retorna código HTTP 200 si todo está "healthy", 500 si hay problemas
- Útil para verificar despliegues en producción

### 4. Migraciones de Base de Datos

#### a) Campos lastname y group
**Archivo:** `database/migrations/2025_10_22_221516_add_lastname_and_group_to_users_table.php`

- Agregado campo `lastname` (nullable) después de `name`
- Agregado campo `group` (nullable, indexed) después de `profile`
- Índice en `group` para búsquedas rápidas

#### b) Google ID Nullable
**Archivo:** `database/migrations/2025_10_22_222553_make_google_id_nullable_in_users_table.php`

- Modificado `google_id` para ser nullable
- Removido constraint unique de `google_id` (si existía)
- Agregado índice en `email` para búsquedas en CSV upload (si no existía)
- **Razón:** Permitir crear usuarios desde CSV antes de su primer login
- **Nota:** Incluye validación para evitar errores si los índices ya existen

#### c) Name Nullable
**Archivo:** `database/migrations/2025_10_23_020407_make_name_and_picture_nullable_in_users_table.php`

- Modificado `name` para ser nullable
- **Razón:** Permitir crear usuarios desde CSV sin nombre (se completará en primer login)
- Campo `picture` ya era nullable desde la migración original

### 5. Modelo User

**Archivo:** `app/Models/User.php`

- Agregados `lastname` y `group` a `$fillable`

### 6. AuthController Actualizado

**Archivo:** `app/Http/Controllers/AuthController.php`

Mejoras en el proceso de login:

1. **Separación de nombres:**
   - Usa `given_name` de Google para el campo `name`
   - Usa `family_name` de Google para el campo `lastname`
   - Fallback al nombre completo si no hay `given_name`

2. **Soporte para usuarios CSV:**
   - Primero busca por `google_id`
   - Si no existe, busca por `email` (para usuarios creados por CSV)
   - Actualiza `google_id`, `name`, `lastname` y `picture` en el primer login
   - Si no existe, crea nuevo usuario con perfil student

---

## Estructura de Datos

### Tabla users (actualizada)

```sql
id (bigint)
google_id (varchar, nullable, sin unique)
email (varchar, indexed)
name (varchar, nullable) -- Primer nombre (nullable para usuarios CSV)
lastname (varchar, nullable) -- Apellido
picture (text, nullable)
profile (enum: student, teacher, admin)
group (varchar, nullable, indexed)
created_at (timestamp)
updated_at (timestamp)
```

### Respuesta de listUsers

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "google_id": "...",
      "email": "usuario@example.com",
      "name": "Juan",
      "lastname": "Pérez",
      "picture": "...",
      "profile": "student",
      "group": "Grupo A",
      "game_sessions_count": 5,
      "created_at": "2025-10-22T10:00:00Z",
      "updated_at": "2025-10-22T10:00:00Z"
    }
  ],
  "pagination": {
    "total": 50,
    "per_page": 15,
    "current_page": 1,
    "last_page": 4,
    "from": 1,
    "to": 15
  }
}
```

### Respuesta de uploadCsv

```json
{
  "success": true,
  "message": "Carga completada",
  "stats": {
    "created": 10,
    "updated": 5,
    "errors": 2
  },
  "error_details": [
    "Fila 5: Email inválido (invalid@)",
    "Fila 12: Formato inválido"
  ]
}
```

---

## Comandos Ejecutados

```bash
# Crear middleware
php artisan make:middleware RequireAdmin

# Crear controlador
php artisan make:controller AdminController

# Crear migraciones
php artisan make:migration add_lastname_and_group_to_users_table
php artisan make:migration make_google_id_nullable_in_users_table
php artisan make:migration make_name_and_picture_nullable_in_users_table

# Ejecutar migraciones
php artisan migrate
```

---

## Validaciones y Seguridad

1. **Middleware en cadena:**
   - `auth.google` valida token de Google
   - `require.admin` valida perfil de usuario

2. **Validación de CSV:**
   - Verifica formato de archivo (CSV/TXT, max 2MB)
   - Valida encabezados requeridos: `email`, `group` (obligatorios)
   - Columnas opcionales: `name`, `lastname`
   - Valida cada email con `FILTER_VALIDATE_EMAIL`
   - Manejo de errores por fila
   - Salta filas vacías automáticamente

3. **Protección de datos:**
   - Solo admins pueden acceder a endpoints `/api/admin/*`
   - Retorna 403 Forbidden si no es admin

---

## Testing Manual Recomendado

1. ✅ **Verificado:** Usuarios no-admin reciben 403 en endpoints admin
2. ✅ **Verificado:** Listar usuarios con paginación funciona correctamente
3. ✅ **Verificado:** Ver sesiones de un usuario específico funciona
4. ✅ **Verificado:** Cargar CSV válido (formato mínimo: email,group)
5. ✅ **Verificado:** Cargar CSV con name y lastname opcionales
6. ✅ **Verificado:** Manejo de errores en CSV con emails inválidos
7. **Pendiente:** Login con usuario creado por CSV (debe actualizar google_id y nombres)

---

## Notas Técnicas

- **Eager Loading:** Se usa `with('shots')` para evitar N+1 queries
- **Soft Deletes:** No implementado (puede agregarse en futuro)
- **Índices:** Agregados en `email` y `group` para mejorar performance
- **CSV Encoding:** Soporta UTF-8, usar `str_getcsv` para parsing
- **Health Check:** Los endpoints `/test` y `/health` son públicos y no requieren autenticación, útiles para monitoreo y despliegues

---

## Estado de Implementación

✅ **Completado:**
- Middleware RequireAdmin
- AdminController con 3 endpoints
- Rutas protegidas /api/admin/*
- Migraciones para nullable fields
- CSV upload con columnas opcionales
- Manejo de errores y validaciones
- Frontend AdminScene (ver bitácora frontend)

## Próximos Pasos Sugeridos (Futuros incrementos)

- **Filtros avanzados:** Filtrar usuarios por perfil, grupo, fecha de registro
- **Búsqueda:** Buscar usuarios por email o nombre
- **Exportar datos:** Exportar lista de usuarios a CSV
- **Estadísticas del sistema:** Panel de estadísticas globales (INCREMENTO 6 pendiente)
- **Gestión de perfiles:** Cambiar perfil de usuarios desde el admin
- **Gestión de grupos:** CRUD de grupos predefinidos
- **Soft deletes:** Implementar borrado lógico de usuarios
- **Logs de auditoría:** Registrar acciones administrativas
