# Multiplication Shooter - Backend

API REST para el juego educativo **Multiplication Shooter**, desarrollada con Laravel.

## Descripcion del Proyecto

Backend que proporciona:
- Autenticacion con Google OAuth
- Gestion de sesiones de juego
- Registro de disparos (aciertos/errores)
- Estadisticas y progreso del usuario
- Panel de administracion con carga masiva de usuarios via CSV

## Tecnologias

- **PHP:** 8.2+
- **Laravel:** 12.x
- **MySQL:** 8.0+ / MariaDB 10.11+
- **Google API Client:** Para verificacion de tokens JWT

## Requisitos Previos

- PHP 8.2 o superior
- Composer 2.x
- MySQL 8.0+ o MariaDB 10.11+
- Extensiones PHP: `pdo_mysql`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`

## Instalacion

### 1. Clonar el repositorio

```bash
git clone git@github.com:rodanmuro/multiplicacion-shooter-backend.git
cd multiplicacion-shooter-backend
```

### 2. Instalar dependencias

```bash
composer install
```

### 3. Configurar variables de entorno

```bash
cp .env.example .env
```

Editar `.env` con tus credenciales:

```env
APP_NAME="Multiplication Shooter"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=multiplication_shooter
DB_USERNAME=root
DB_PASSWORD=tu_password

GOOGLE_CLIENT_ID=tu_google_client_id
```

### 4. Crear la base de datos

```sql
CREATE DATABASE multiplication_shooter;
```

### 5. Generar clave de aplicacion

```bash
php artisan key:generate
```

### 6. Ejecutar migraciones

```bash
php artisan migrate
```

## Levantar la Aplicacion

### Desarrollo local

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

La API estara disponible en `http://localhost:8000/api`

### Verificar que funciona

```bash
curl http://localhost:8000/api/test
# Respuesta: {"message":"API is working!","timestamp":"..."}

curl http://localhost:8000/api/health
# Respuesta: {"status":"healthy","checks":{...}}
```

## Estructura de la Base de Datos

### Tablas principales

| Tabla | Descripcion |
|-------|-------------|
| `users` | Usuarios autenticados con Google |
| `user_logins` | Registro de cada inicio de sesion |
| `game_sessions` | Sesiones de juego (5 minutos cada una) |
| `shots` | Disparos registrados en cada sesion |

### Diagrama de relaciones

```
users (1) ──────── (N) user_logins
  │
  └── (1) ──────── (N) game_sessions
                          │
                          └── (1) ──── (N) shots
```

## Endpoints de la API

### Publicos (sin autenticacion)

| Metodo | Endpoint | Descripcion |
|--------|----------|-------------|
| GET | `/api/test` | Verificar que la API responde |
| GET | `/api/health` | Estado completo del sistema |
| POST | `/api/auth/verify` | Verificar token de Google y registrar usuario |

### Protegidos (requieren token de Google)

| Metodo | Endpoint | Descripcion |
|--------|----------|-------------|
| GET | `/api/sessions` | Listar sesiones del usuario (paginado) |
| GET | `/api/sessions/{id}` | Detalle de sesion con disparos |
| POST | `/api/sessions` | Crear nueva sesion de juego |
| PUT | `/api/sessions/{id}/finish` | Finalizar sesion con estadisticas |
| POST | `/api/sessions/{id}/shots` | Registrar disparo |

### Administracion (requieren perfil admin)

| Metodo | Endpoint | Descripcion |
|--------|----------|-------------|
| GET | `/api/admin/users` | Listar todos los usuarios (paginado) |
| GET | `/api/admin/users/{id}/sessions` | Ver sesiones de un usuario |
| POST | `/api/admin/users/upload-csv` | Carga masiva de usuarios via CSV |

## Autenticacion

La API usa tokens JWT de Google para autenticacion:

1. El frontend obtiene un token de Google Identity Services
2. Envia el token en el header: `Authorization: Bearer {token}`
3. El middleware `auth.google` valida el token con Google
4. Si es valido, el usuario queda autenticado para el request

## Formato CSV para Carga Masiva

### Columnas obligatorias
- `email`: Email del usuario
- `group`: Grupo/curso del estudiante

### Columnas opcionales
- `name`: Nombre
- `lastname`: Apellido

### Ejemplo

```csv
email,group,name,lastname
estudiante1@school.edu,5to A,Maria,Garcia
estudiante2@school.edu,5to B,Juan,Perez
```

## Perfiles de Usuario

| Perfil | Permisos |
|--------|----------|
| `student` | Jugar y ver sus propias estadisticas |
| `teacher` | Igual que student (expansion futura) |
| `admin` | Acceso al panel de administracion |

## Comandos Utiles

```bash
# Ver rutas registradas
php artisan route:list --path=api

# Limpiar cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Ejecutar tests
php artisan test

# Revertir y re-ejecutar migraciones (CUIDADO: borra datos)
php artisan migrate:fresh
```

## Despliegue en Produccion

Ver bitacora: `bitacoras/008_28_10_2025_Despliegue_Produccion_hosting_compartido.md`

### Consideraciones importantes

1. Configurar `APP_ENV=production` y `APP_DEBUG=false`
2. Ejecutar `composer install --no-dev --optimize-autoloader`
3. Configurar CORS para el dominio del frontend
4. Asegurar permisos de escritura en `storage/` y `bootstrap/cache/`

## Estructura del Proyecto

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php      # Login con Google
│   │   ├── GameSessionController.php # Sesiones de juego
│   │   ├── ShotController.php      # Registro de disparos
│   │   └── AdminController.php     # Panel de admin
│   ├── Middleware/
│   │   ├── ValidateGoogleToken.php # Validacion de JWT
│   │   └── RequireAdmin.php        # Verificar perfil admin
│   ├── Requests/                   # Validacion de requests
│   └── Resources/                  # Transformacion de respuestas
├── Models/
│   ├── User.php
│   ├── UserLogin.php
│   ├── GameSession.php
│   └── Shot.php
└── Services/
    └── GoogleAuthService.php       # Verificacion de tokens

config/
├── cors.php                        # Configuracion CORS
└── services.php                    # Google Client ID

database/
└── migrations/                     # Migraciones de BD

routes/
└── api.php                         # Rutas de la API
```

## Bitacoras de Desarrollo

El desarrollo se documento en incrementos:

1. `001` - Setup inicial de Laravel
2. `002` - Autenticacion con Google OAuth
3. `003` - Crear sesiones de juego
4. `004` - Registrar disparos
5. `005` - Finalizar sesion con estadisticas
6. `006` - Historial y detalle de sesiones
7. `007` - Panel de administracion y CSV
8. `008` - Despliegue a produccion

## Licencia

Proyecto educativo desarrollado para el Colegio CEVU.
