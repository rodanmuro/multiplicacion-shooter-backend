# Bitácora Backend - Despliegue a Producción
**Fecha:** 28 de octubre de 2025
**Responsable:** Claude Code
**Tema:** Primer despliegue del backend Laravel a hosting compartido

---

## Resumen

Primer despliegue exitoso del backend Laravel del proyecto Multiplication Shooter a hosting compartido (voyeducando.com) usando solo acceso FTP. Se implementó solución para rutas en subdirectorio y se configuró correctamente la base de datos.

---

## Información del Servidor

**Hosting:** voyeducando.com (Hosting compartido)
**Acceso:** FTP (ftp.voyeducando.com:21)
**Usuario:** heverdar
**PHP:** 8.2.29
**Base de datos:** MariaDB 10.11.14
**Sin acceso SSH**

**Rutas en servidor:**
```
/home/heverdar/multiplication-shooter/          # Backend Laravel (privado)
/home/heverdar/public_html/multiplicacion/api/  # Carpeta pública
```

**URL base:** `https://voyeducando.com/multiplicacion/api/`

---

## Proceso de Despliegue

### 1. Preparación Local

```bash
# Instalar dependencias de producción
cd multiplicacion-shooter-backend
composer install --no-dev --optimize-autoloader

# Crear archivo comprimido
zip -r backend.zip . \
  -x "*.git*" \
  -x ".env" \
  -x "storage/logs/*" \
  -x "storage/framework/cache/data/*" \
  -x "storage/framework/sessions/*" \
  -x "storage/framework/views/*" \
  -x "tests/*" \
  -x "phpunit.xml"
```

### 2. Subida vía FTP

- Subir `backend.zip` a `/home/heverdar/`
- Descomprimir vía cPanel File Manager
- Renombrar carpeta extraída a `multiplication-shooter`

### 3. Configuración de Permisos

Establecer permisos 755 en:
```
storage/
storage/app/
storage/framework/
storage/framework/cache/
storage/framework/sessions/
storage/framework/views/
storage/logs/
bootstrap/cache/
```

### 4. Creación de Base de Datos

**vía cPanel → MySQL Database Wizard:**
- Base de datos: `heverdar_multiplication_shooter`
- Usuario: `heverdar_multuser`
- Privilegios: Todos

### 5. Archivo .env

Creado en `/home/heverdar/multiplication-shooter/.env`:

```env
APP_NAME="Multiplication Shooter"
APP_ENV=production
APP_KEY=                              # Generado por install.php
APP_DEBUG=false
APP_URL=https://voyeducando.com/multiplicacion

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=heverdar_multiplication_shooter
DB_USERNAME=heverdar_multuser
DB_PASSWORD=[contraseña]

GOOGLE_CLIENT_ID=[client_id]
GOOGLE_CLIENT_SECRET=[client_secret]
GOOGLE_REDIRECT_URI=https://voyeducando.com/multiplicacion/auth/google/callback

SESSION_DRIVER=database
```

### 6. Script de Instalación

**Archivo:** `install.php` (temporal, eliminado después de uso)

```php
<?php
// Carga Laravel y ejecuta:
Artisan::call('key:generate', ['--force' => true]);
Artisan::call('migrate', ['--force' => true]);
Artisan::call('config:clear');
Artisan::call('cache:clear');
Artisan::call('route:clear');
```

**Ejecución:** `https://voyeducando.com/multiplicacion/api/install.php`

**Resultado:**
```
✅ APP_KEY generado
✅ Migraciones completadas (10 migraciones)
✅ Cache limpiado
```

### 7. Configuración de index.php

**Problema inicial:** Rutas no funcionaban sin `index.php` en la URL

**Causa:** Laravel en subdirectorio recibía REQUEST_URI con prefijo incorrecto

**Solución:** Modificar `index.php` para ajustar REQUEST_URI

---

## Problema Encontrado y Solución

### Problema: Error 404 en todas las rutas API

**Síntoma:**
```bash
curl https://voyeducando.com/multiplicacion/api/test
→ 404 Not Found (página de Laravel)
```

**Diagnóstico:**

1. **Rutas registradas correctamente:**
   ```bash
   php artisan route:list
   → api/test ✅
   → api/health ✅
   ```

2. **Laravel funcionando:**
   - Test con scripts PHP directos funcionaba
   - Base de datos conectada correctamente
   - Migraciones aplicadas

3. **REQUEST_URI incorrecto:**
   ```
   Recibido: /multiplicacion/api/test
   Laravel buscaba: /multiplicacion/api/test
   Debería buscar: /api/test
   ```

### Causa Raíz

Laravel está configurado para servir rutas API con prefijo `/api` (definido en `bootstrap/app.php`), pero cuando se despliega en un subdirectorio (`/multiplicacion/api/`), el REQUEST_URI incluye el subdirectorio completo, causando que Laravel no encuentre las rutas.

### Solución Implementada

**Archivo:** `/home/heverdar/public_html/multiplicacion/api/index.php`

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../../../multiplication-shooter/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../../../multiplication-shooter/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../../../multiplication-shooter/bootstrap/app.php';

// Fix for subfolder deployment: Remove ONLY /multiplicacion from REQUEST_URI
// Keep /api so Laravel can match the routes correctly
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// Remove /multiplicacion prefix but keep /api
$basePath = '/multiplicacion';
if (strpos($requestUri, $basePath) === 0) {
    $newUri = substr($requestUri, strlen($basePath));
    if (empty($newUri) || $newUri[0] !== '/') {
        $newUri = '/' . $newUri;
    }
    $_SERVER['REQUEST_URI'] = $newUri;
}

// Capture the request with the fixed URI
$request = Request::capture();

$app->handleRequest($request);
```

**Explicación del fix:**

1. **Captura REQUEST_URI original:** `/multiplicacion/api/test`
2. **Remueve solo `/multiplicacion`:** Resultado → `/api/test`
3. **Mantiene `/api`:** Laravel encuentra la ruta correctamente
4. **Reconstruye request:** Con el URI corregido

### Verificación de la Solución

```bash
# Test endpoint
curl https://voyeducando.com/multiplicacion/api/test
→ {"message":"API is working!","timestamp":"2025-10-28T02:48:30.110125Z"}

# Health check
curl https://voyeducando.com/multiplicacion/api/health
→ {"status":"healthy","checks":{...}}
```

✅ **Todas las rutas funcionando correctamente sin index.php en la URL**

---

## Endpoints Disponibles

### Públicos (sin autenticación)

```bash
GET  /api/test                      # Prueba básica
GET  /api/health                    # Estado del sistema
POST /api/auth/verify               # Verificación de token Google
```

### Protegidos (requieren autenticación)

```bash
GET  /api/sessions                  # Listar sesiones del usuario
POST /api/sessions                  # Crear nueva sesión
GET  /api/sessions/{id}             # Obtener sesión específica
PUT  /api/sessions/{id}/finish      # Finalizar sesión
POST /api/sessions/{id}/shots       # Registrar disparos
```

### Admin (requieren autenticación + perfil admin)

```bash
GET  /api/admin/users                      # Listar usuarios
GET  /api/admin/users/{id}/sessions        # Ver sesiones de usuario
POST /api/admin/users/upload-csv           # Carga masiva CSV
```

---

## Estado Final del Sistema

**Resultado del health check:**

```json
{
  "status": "healthy",
  "checks": {
    "laravel": true,
    "php_version": "8.2.29",
    "database": true,
    "storage_writable": true,
    "cache_writable": true,
    "env_loaded": true,
    "app_key_set": true,
    "database_name": "heverdar_multiplication_shooter",
    "tables": {
      "users": true,
      "game_sessions": true,
      "shots": true
    }
  },
  "timestamp": "2025-10-28T02:48:38.692532Z",
  "environment": "local"
}
```

---

## Archivos de Diagnóstico Creados (Temporales)

Durante el troubleshooting se crearon varios archivos de diagnóstico que deben eliminarse:

```
/public_html/multiplicacion/api/install.php         # ⚠️ ELIMINAR (ya ejecutado)
/public_html/multiplicacion/api/test-simple.php     # ⚠️ ELIMINAR
/public_html/multiplicacion/api/check-htaccess.php  # ⚠️ ELIMINAR
/public_html/multiplicacion/api/show-error.php      # ⚠️ ELIMINAR
/public_html/multiplicacion/api/show-index.php      # ⚠️ ELIMINAR
/public_html/multiplicacion/api/debug-request.php   # ⚠️ ELIMINAR
/public_html/multiplicacion/api/clear-cache.php     # ⚠️ ELIMINAR
/public_html/multiplicacion/api/verify-index.php    # ⚠️ ELIMINAR
/public_html/multiplicacion/api/list-api-files.php  # ⚠️ ELIMINAR
```

**IMPORTANTE:** Estos archivos contienen información sensible del sistema y deben eliminarse por seguridad.

---

## Archivos Importantes en Producción

### Mantener:

```
/public_html/multiplicacion/api/index.php           # ✅ Entry point (con fix de subdirectorio)
/public_html/multiplicacion/api/.htaccess           # ✅ Reglas de rewrite
```

### Estructura backend (privada):

```
/home/heverdar/multiplication-shooter/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/                 # No se usa (contenido copiado a /api/)
├── routes/
├── storage/                # Con permisos 755
├── vendor/
├── .env                    # Credenciales de producción
└── artisan
```

---

## Lecciones Aprendidas

### 1. Despliegue en Subdirectorio

**Problema:** Laravel asume que está en la raíz del dominio
**Solución:** Ajustar REQUEST_URI en index.php para remover prefijo del subdirectorio

### 2. Sin Acceso SSH

**Limitación:** No se puede ejecutar `php artisan` directamente
**Solución:** Scripts PHP que llaman `Artisan::call()` ejecutados vía navegador

### 3. Compresión para Subida

**Ventaja:** Subir 1 archivo .zip es mucho más rápido que 5000+ archivos
**Beneficio:** Integridad garantizada, vendor/ incluido

### 4. Diagnóstico con Scripts PHP

**Estrategia:** Crear scripts de diagnóstico que se ejecutan vía web
**Resultado:** Información detallada del servidor sin SSH

---

## Comandos Útiles para Futuros Despliegues

### Limpiar caché vía PHP script:

```php
Artisan::call('config:clear');
Artisan::call('cache:clear');
Artisan::call('route:clear');
Artisan::call('view:clear');
```

### Verificar estado del sistema:

```bash
curl https://voyeducando.com/multiplicacion/api/health
```

### Ver rutas registradas:

```php
Artisan::call('route:list', ['--path' => 'api']);
echo Artisan::output();
```

---

## Problema y Solución - Estadísticas de Sesiones en Admin Panel

**Fecha:** 28 de octubre de 2025

### Problema Encontrado

Al acceder a las estadísticas de un estudiante desde el panel de administración, se presentaba el siguiente error:

```
TypeError: can't access property "toString", o.total_shots is undefined
```

**Archivo afectado:** [AdminScene.ts:453](../../multiplicacion-shooter-frontend/src/scenes/AdminScene.ts#L453)

**Causa:**
El frontend esperaba campos agregados en cada sesión (`total_shots`, `correct_shots`, `wrong_shots`, `accuracy`), pero el backend solo retornaba el array `shots` sin calcular estas estadísticas.

**Respuesta del backend (incorrecta):**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "final_score": 600,
      "shots": [
        {"id": 47, "is_correct": true, ...},
        {"id": 48, "is_correct": true, ...}
      ]
      // Faltaban: total_shots, correct_shots, wrong_shots, accuracy
    }
  ]
}
```

### Solución Implementada

**Archivo modificado:** [AdminController.php:84-108](../app/Http/Controllers/AdminController.php#L84-L108)

Se agregó procesamiento para calcular estadísticas agregadas en el método `getUserSessions()`:

```php
// Agregar estadísticas calculadas a cada sesión
$sessionsWithStats = $sessions->getCollection()->map(function ($session) {
    $totalShots = $session->shots->count();
    $correctShots = $session->shots->where('is_correct', true)->count();
    $wrongShots = $totalShots - $correctShots;
    $accuracy = $totalShots > 0 ? round(($correctShots / $totalShots) * 100, 2) : 0;

    return [
        'id' => $session->id,
        'user_id' => $session->user_id,
        'started_at' => $session->started_at,
        'finished_at' => $session->finished_at,
        'final_score' => $session->final_score,
        'max_level_reached' => $session->max_level_reached,
        'duration_seconds' => $session->duration_seconds,
        'canvas_width' => $session->canvas_width,
        'canvas_height' => $session->canvas_height,
        'total_shots' => $totalShots,
        'correct_shots' => $correctShots,
        'wrong_shots' => $wrongShots,
        'accuracy' => $accuracy,
        'created_at' => $session->created_at,
        'updated_at' => $session->updated_at,
    ];
});
```

**Respuesta del backend (correcta):**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "final_score": 600,
      "total_shots": 109,
      "correct_shots": 60,
      "wrong_shots": 49,
      "accuracy": 55.05
    }
  ]
}
```

### Resultado

✅ El panel de administración ahora muestra correctamente:
- Disparos totales
- Aciertos
- Precisión (%)

✅ Error resuelto en producción

---

## Próximos Pasos

- [x] Eliminar archivos de diagnóstico temporal
- [x] Configurar frontend para usar URL base de producción
- [x] Crear usuario administrador para pruebas
- [x] Fix estadísticas del admin panel
- [ ] Implementar GitHub Actions para despliegues automatizados
- [ ] Considerar script `migrate.php` para futuras actualizaciones
- [ ] Documentar proceso de rollback en caso de errores

---

## Conclusión

✅ **Despliegue exitoso del backend a producción**
✅ **Todas las rutas API funcionando correctamente**
✅ **Base de datos configurada y migraciones aplicadas**
✅ **Sistema de salud (health check) operativo**
✅ **Solución implementada para subdirectorio**
✅ **Panel de administración funcionando completamente**

El backend está completamente operativo en producción y listo para ser consumido por el frontend.

---

## Referencias

- URL base API: `https://voyeducando.com/multiplicacion/api/`
- Base de datos: `heverdar_multiplication_shooter`
- Servidor: voyeducando.com (Hosting compartido)
- PHP: 8.2.29
- Laravel: 11.x
