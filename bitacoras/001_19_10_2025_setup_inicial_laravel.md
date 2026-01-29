# Bitácora 001 - 19/10/2025

## Setup Inicial del Backend - Laravel 12

---

## Contexto del Proyecto

Backend API REST para el juego educativo **Multiplication Shooter** (Phaser 3 + TypeScript).

**Objetivo:** Proporcionar autenticación con Google OAuth, gestión de sesiones de juego, registro de disparos y análisis de estadísticas.

---

## Lo que se realizó hoy

### 1. ✅ Verificación de Requisitos del Sistema

```bash
PHP: 8.2.29 ✅
Composer: 2.2.6 ✅
MySQL: 8.0.43 ✅
```

### 2. ✅ Instalación de Laravel 12

```bash
composer create-project laravel/laravel . --prefer-dist
```

**Versión instalada:** Laravel 12.34.0

> **Nota:** Se instaló Laravel 12 (más reciente) en lugar de Laravel 11 como se planeó originalmente.

### 3. ✅ Configuración de Base de Datos

**Base de datos creada:**
```sql
CREATE DATABASE multiplication_shooter;
```

**Configuración en `.env`:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=multiplication_shooter
DB_USERNAME=root
DB_PASSWORD=12345678
```

**Conexión verificada:** `php artisan db:show` ✅

### 4. ✅ Instalación de Google API Client

```bash
composer require google/apiclient
```

**Versión:** v2.18.4

**Variable agregada al `.env`:**
```env
GOOGLE_CLIENT_ID=
```

### 5. ✅ Configuración de CORS

**Archivo creado:** `config/cors.php`

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => ['http://localhost:5173'], // Frontend
'supports_credentials' => true,
```

**Middleware agregado en `bootstrap/app.php`**

### 6. ✅ Estructura de Rutas API

**Archivo creado:** `routes/api.php`

**Endpoint de prueba:**
```
GET /api/test
Response: {"message":"API is working!"}
```

### 7. ✅ Prueba del Servidor

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

**Prueba de CORS exitosa:**
```bash
✅ Status: 200 OK
✅ Access-Control-Allow-Origin: http://localhost:5173
✅ Access-Control-Allow-Credentials: true
```

### 8. ✅ Repositorio Git

**Commit inicial:**
```
feat: initial Laravel 12 setup with MySQL and CORS configuration
```

**Commit hash:** `1fbdd2f`
**Push a GitHub:** ✅ Completado

---

## Estado Actual

### ✅ Completado

- [x] Laravel 12 instalado y funcionando
- [x] MySQL conectado (BD: `multiplication_shooter`)
- [x] Google API Client instalado
- [x] CORS configurado para frontend (localhost:5173)
- [x] Rutas API habilitadas con endpoint de prueba
- [x] Repositorio sincronizado con GitHub

### 📁 Estructura Principal

```
multiplicacion-shooter-backend/
├── config/
│   └── cors.php         (✅ Creado y configurado)
├── routes/
│   └── api.php          (✅ Creado con ruta de prueba)
├── bootstrap/
│   └── app.php          (✅ CORS y rutas API habilitadas)
└── .env                 (✅ MySQL configurado)
```

---

## Siguiente Paso: INCREMENTO 1 - Autenticación

**Objetivo:** Usuario puede loguearse con Google OAuth y quedar registrado en la base de datos

**Duración estimada:** 6 horas (2-3h backend + 2h frontend + testing)

### Resumen del INCREMENTO 1:

**Backend:**
- Crear migraciones de `users` y `user_logins`
- Crear modelos Eloquent
- Crear `GoogleAuthService` para verificar tokens
- Crear `AuthController` con endpoint `POST /api/auth/verify`

**Frontend:**
- Instalar Axios
- Crear servicio API con interceptores
- Integrar con `LoginScene`

**Testing:**
- Validar que login crea registros correctamente
- Validar que NO se duplican usuarios
- Validar que se registran múltiples logins

### ✋ CHECKPOINT 1

No avanzar al INCREMENTO 2 sin:
- ✅ Tests automáticos pasando
- ✅ Validación manual completada
- ✅ Registros en BD correctos

---

## Comandos Útiles

```bash
# Servidor
php artisan serve --host=0.0.0.0 --port=8000

# Base de datos
php artisan db:show
php artisan migrate

# Crear archivos
php artisan make:migration create_tabla_table
php artisan make:model NombreModelo
php artisan make:controller NombreController

# Testing
php artisan test
php artisan test --filter NombreTest
```

---

**Documento creado por:** Claude (Sonnet 4.5)
**Fecha:** 19 de octubre de 2025
**Estado:** Setup inicial completado - Listo para INCREMENTO 1
