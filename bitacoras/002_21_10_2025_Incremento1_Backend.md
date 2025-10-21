# Bitácora 002 - 21/10/2025

## INCREMENTO 1 - Backend: Autenticación con Google OAuth

---

## Objetivo del Incremento

Implementar el backend para autenticación con Google OAuth:
1. Recibir token JWT del frontend
2. Validar token con Google
3. Crear/actualizar usuario en tabla `users`
4. Registrar login en tabla `user_logins`
5. Retornar datos del usuario al frontend

---

## ✅ Tareas Completadas

### 1. Migración de Tabla `users`

**Archivo:** `database/migrations/2025_10_21_125435_create_users_table.php`

**Estructura:**
```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('google_id')->unique();
    $table->string('email');
    $table->string('name');
    $table->text('picture')->nullable();
    $table->enum('profile', ['student', 'teacher', 'admin'])->default('student');
    $table->timestamps();

    // Índices
    $table->index('email');
    $table->index('profile');
});
```

**Verificación:**
```bash
php artisan db:table users
```

✅ Tabla creada con 8 columnas, índices y constraints correctos.

---

### 2. Migración de Tabla `user_logins`

**Archivo:** `database/migrations/2025_10_21_125553_create_user_logins_table.php`

**Estructura:**
```php
Schema::create('user_logins', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->timestamp('logged_in_at');
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamps();

    // Índices
    $table->index(['user_id', 'logged_in_at']);
});
```

**Verificación:**
```bash
php artisan db:table user_logins
```

✅ Tabla creada con 7 columnas, foreign key a `users`, índices correctos.

---

### 3. Ejecución de Migraciones

**Comando:**
```bash
php artisan migrate
```

**Resultado:**
```
✅ 2025_10_21_125435_create_users_table ............ DONE
✅ 2025_10_21_125553_create_user_logins_table ...... DONE
```

---

### 4. Modelo `User`

**Archivo:** `app/Models/User.php`

**Características implementadas:**

#### Constantes de Perfil:
```php
const PROFILE_STUDENT = 'student';
const PROFILE_TEACHER = 'teacher';
const PROFILE_ADMIN = 'admin';
```

#### Campos Mass Assignable:
```php
protected $fillable = [
    'google_id',
    'email',
    'name',
    'picture',
    'profile'
];
```

#### Relaciones:
- `logins()`: HasMany → UserLogin
- `gameSessions()`: HasMany → GameSession (comentado para INCREMENTO 2)

#### Métodos Helper:
- `isAdmin(): bool`
- `isTeacher(): bool`
- `isStudent(): bool`

---

### 5. Modelo `UserLogin`

**Archivo:** `app/Models/UserLogin.php`

**Características implementadas:**

#### Campos Mass Assignable:
```php
protected $fillable = [
    'user_id',
    'logged_in_at',
    'ip_address',
    'user_agent'
];
```

#### Casts:
```php
'logged_in_at' => 'datetime',
'created_at' => 'datetime',
'updated_at' => 'datetime',
```

#### Relaciones:
- `user()`: BelongsTo → User

---

### 6. GoogleAuthService

**Archivo:** `app/Services/GoogleAuthService.php`

**Responsabilidades:**
- Verificar tokens JWT de Google
- Validar expiración
- Extraer datos del usuario

**Método principal:**
```php
public function verifyToken(string $token): array
```

**Retorna:**
```php
[
    'sub' => $payload['sub'],              // Google ID
    'email' => $payload['email'],
    'name' => $payload['name'],
    'picture' => $payload['picture'] ?? null,
    'given_name' => $payload['given_name'] ?? null,
    'family_name' => $payload['family_name'] ?? null,
    'email_verified' => $payload['email_verified'] ?? false,
]
```

**Manejo de errores:**
- Lanza `Exception` si el token es inválido
- Mensaje descriptivo del error

---

### 7. Configuración de Google Client ID

**Archivo modificado:** `config/services.php`

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
],
```

**Archivo `.env`:**
```env
GOOGLE_CLIENT_ID=820391133137-u77dkpun8sc7u77vd8fpumosfr0tbuee.apps.googleusercontent.com
```

---

### 8. AuthController

**Archivo:** `app/Http/Controllers/AuthController.php`

**Endpoint implementado:** `POST /api/auth/verify`

**Flujo del método `verify()`:**

```php
1. Validar request
   ↓
2. Verificar token con GoogleAuthService
   ↓
3. Buscar o crear usuario (User::firstOrCreate)
   - Si NO existe → crear con profile='student'
   - Si existe → NO duplicar
   ↓
4. SIEMPRE crear registro en user_logins
   - user_id
   - logged_in_at (now())
   - ip_address ($request->ip())
   - user_agent ($request->userAgent())
   ↓
5. Retornar usuario
```

**Request esperado:**
```json
POST /api/auth/verify
Content-Type: application/json

{
  "token": "eyJhbGciOiJSUzI1NiIsImtpZCI6IjA1..."
}
```

**Response exitoso:**
```json
HTTP 200 OK

{
  "success": true,
  "data": {
    "id": 1,
    "google_id": "106839451234567890123",
    "email": "usuario@gmail.com",
    "name": "Juan Pérez",
    "picture": "https://lh3.googleusercontent.com/...",
    "profile": "student",
    "created_at": "2025-10-21T12:30:00.000000Z",
    "updated_at": "2025-10-21T12:30:00.000000Z"
  }
}
```

**Response con error:**
```json
HTTP 401 Unauthorized

{
  "success": false,
  "error": "Error al verificar token de Google",
  "message": "Token inválido o expirado"
}
```

---

### 9. Ruta API

**Archivo:** `routes/api.php`

```php
use App\Http\Controllers\AuthController;

Route::post('/auth/verify', [AuthController::class, 'verify']);
```

**Verificación:**
```bash
php artisan route:list --path=api
```

**Output:**
```
POST  api/auth/verify ........... AuthController@verify ✅
```

---

## 📁 Archivos Creados/Modificados

### Archivos Creados (6):
1. ✅ `database/migrations/2025_10_21_125435_create_users_table.php`
2. ✅ `database/migrations/2025_10_21_125553_create_user_logins_table.php`
3. ✅ `app/Models/UserLogin.php`
4. ✅ `app/Services/GoogleAuthService.php`
5. ✅ `app/Http/Controllers/AuthController.php`
6. ✅ `bitacoras/002_21_10_2025_Incremento1_Backend.md` (esta bitácora)

### Archivos Modificados (3):
1. ✅ `app/Models/User.php` - Adaptado para Google OAuth
2. ✅ `config/services.php` - Agregada configuración de Google
3. ✅ `routes/api.php` - Agregada ruta `/auth/verify`

---

## 🎯 Próximos Pasos: Testing

### Testing Manual:

**Requisitos:**
1. ✅ Backend corriendo en `http://localhost:8000`
2. ✅ Frontend corriendo en `http://localhost:5173`
3. ✅ Base de datos MySQL activa

**Checklist de Validación:**

- [ ] **Iniciar servidor backend:**
  ```bash
  cd ~/proyectos/.../multiplicacion-shooter-backend
  php artisan serve --host=0.0.0.0 --port=8000
  ```

- [ ] **Iniciar servidor frontend:**
  ```bash
  cd ~/proyectos/.../multiplicacion-shooter-frontend
  npm run dev
  ```

- [ ] **Primera vez que usuario se loguea:**
  - [ ] Abrir `http://localhost:5173`
  - [ ] Click en "Sign in with Google"
  - [ ] Seleccionar cuenta de Google
  - [ ] Verificar logs en consola del navegador:
    ```
    Enviando token al backend...
    [API Request] POST /auth/verify
    [API Response] 200 /auth/verify
    ✅ Usuario registrado en backend: {datos}
    ```
  - [ ] Verificar en BD:
    ```sql
    SELECT * FROM users;
    -- Debe haber 1 registro con profile='student'

    SELECT * FROM user_logins;
    -- Debe haber 1 registro con logged_in_at, ip_address, user_agent
    ```

- [ ] **Segunda vez que usuario se loguea (mismo google_id):**
  - [ ] Cerrar sesión y volver a hacer login
  - [ ] Verificar que NO se duplica en tabla `users`
  - [ ] Verificar que SÍ se crea nuevo registro en `user_logins`
  - [ ] Verificar logs en consola del navegador

- [ ] **Manejo de errores:**
  - [ ] Detener servidor backend
  - [ ] Intentar login desde frontend
  - [ ] Verificar que muestra: "Error al conectar con el servidor"

---

## 🔍 Comandos Útiles para Debugging

### Ver logs del backend:
```bash
tail -f storage/logs/laravel.log
```

### Ver registros en BD:
```sql
-- Ver usuarios
SELECT * FROM users;

-- Ver logins de un usuario
SELECT ul.*, u.name
FROM user_logins ul
JOIN users u ON ul.user_id = u.id
ORDER BY ul.logged_in_at DESC;

-- Contar logins por usuario
SELECT u.name, COUNT(ul.id) as total_logins
FROM users u
LEFT JOIN user_logins ul ON u.id = ul.user_id
GROUP BY u.id, u.name;
```

### Limpiar base de datos (solo para testing):
```bash
php artisan migrate:fresh
```

---

## 📊 Datos Esperados en Base de Datos

### Tabla `users` (después del primer login):

| id | google_id | email | name | picture | profile | created_at | updated_at |
|----|-----------|-------|------|---------|---------|------------|------------|
| 1 | 106839... | usuario@gmail.com | Juan Pérez | https://... | student | 2025-10-21 12:30:00 | 2025-10-21 12:30:00 |

### Tabla `user_logins` (después de 3 logins):

| id | user_id | logged_in_at | ip_address | user_agent | created_at |
|----|---------|--------------|------------|------------|------------|
| 1 | 1 | 2025-10-21 12:30:00 | 127.0.0.1 | Mozilla/5.0... | 2025-10-21 12:30:00 |
| 2 | 1 | 2025-10-21 14:15:00 | 127.0.0.1 | Mozilla/5.0... | 2025-10-21 14:15:00 |
| 3 | 1 | 2025-10-21 16:45:00 | 127.0.0.1 | Mozilla/5.0... | 2025-10-21 16:45:00 |

---

## ⚠️ Notas Importantes

1. **Google Client ID:**
   - El mismo Client ID está configurado en frontend y backend
   - Ubicación frontend: `.env` → `VITE_GOOGLE_CLIENT_ID`
   - Ubicación backend: `.env` → `GOOGLE_CLIENT_ID`

2. **CORS:**
   - Ya configurado en `config/cors.php`
   - Permite requests desde `http://localhost:5173`

3. **Relación GameSession comentada:**
   - En `app/Models/User.php` línea 67-70
   - Se descomentará en INCREMENTO 2

4. **Dependencia Google API Client:**
   - Ya instalada: `google/apiclient` v2.18.4
   - Usado en `GoogleAuthService`

---

## 🎉 Estado del INCREMENTO 1

### ✅ Backend: COMPLETADO

**Implementado:**
- ✅ Migraciones de `users` y `user_logins`
- ✅ Modelos con relaciones Eloquent
- ✅ GoogleAuthService funcional
- ✅ AuthController con endpoint `/api/auth/verify`
- ✅ Ruta API registrada

### ⏳ Pendiente:

**Testing completo frontend-backend:**
- ⏳ Validar primer login crea usuario
- ⏳ Validar segundo login NO duplica usuario
- ⏳ Validar cada login crea registro en `user_logins`
- ⏳ Validar datos correctos en BD

---

## 🚀 Siguiente Paso: Testing Manual

**Instrucciones:**
1. Iniciar servidor backend: `php artisan serve --host=0.0.0.0 --port=8000`
2. Iniciar servidor frontend: `npm run dev`
3. Abrir navegador en `http://localhost:5173`
4. Hacer login con Google
5. Verificar logs en consola del navegador
6. Verificar registros en base de datos

**Si todo funciona correctamente:**
✅ INCREMENTO 1 completado
→ Proceder a INCREMENTO 2: Crear Sesión de Juego

---

**Documento creado por:** Claude (Sonnet 4.5)
**Fecha:** 21 de octubre de 2025
**Estado:** Backend completado - Listo para testing
