# Bitácora Backend - INCREMENTO 8: Ordenamiento y Paginación Mejorada en Panel Admin
**Fecha:** 4 de febrero de 2026
**Responsable:** Claude Code
**Incremento:** 8 - Mejoras de Ordenamiento y Paginación en Panel de Administración

---

## Resumen

Mejoras significativas en el endpoint `listUsers` del panel de administración para soportar ordenamiento dinámico por múltiples columnas y paginación aumentada. Se incrementó el límite de registros por página de 15 a 40 y se agregaron parámetros `sort_by` y `order` para permitir ordenamiento flexible por diferentes campos.

---

## Cambios Realizados

### 1. Endpoint listUsers Mejorado

**Archivo:** `app/Http/Controllers/AdminController.php`

**Líneas modificadas:** 49-119

#### Nuevos Parámetros Query

Se agregaron dos nuevos parámetros opcionales al endpoint:

```php
/**
 * Query params:
 * - group: Filtrar por grupo
 * - profile: Filtrar por perfil (student, teacher, admin)
 * - search: Buscar por nombre o email
 * - per_page: Resultados por página (default 40)  ← Actualizado de 15 a 40
 * - sort_by: Campo por el cual ordenar           ← NUEVO
 * - order: Orden ascendente o descendente        ← NUEVO
 */
```

#### Campos Ordenables

Se definió un mapeo explícito de campos permitidos para ordenamiento:

```php
$allowedSortFields = [
    'email' => 'email',
    'name' => 'name',
    'group' => 'group',
    'created_at' => 'created_at',
    'sessions_count' => 'game_sessions_count'
];
```

**Nota:** `sessions_count` se mapea a `game_sessions_count` que es el nombre generado automáticamente por Laravel al usar `withCount('gameSessions')`.

#### Lógica de Ordenamiento

```php
// Configurar ordenamiento
$sortBy = $request->input('sort_by', 'created_at');
$order = $request->input('order', 'desc');

// Validar dirección de orden
if (!in_array($order, ['asc', 'desc'])) {
    $order = 'desc';
}

// Obtener usuarios con conteo de sesiones
$perPage = $request->input('per_page', 40);
$query->withCount('gameSessions');

// Aplicar ordenamiento
if (isset($allowedSortFields[$sortBy])) {
    $dbField = $allowedSortFields[$sortBy];
    $query->orderBy($dbField, $order);
} else {
    // Default: ordenar por fecha de creación
    $query->orderBy('created_at', 'desc');
}

$users = $query->paginate($perPage);
```

#### Características de Seguridad

1. **Whitelist de campos:** Solo se permiten campos explícitamente definidos en `$allowedSortFields`
2. **Validación de dirección:** Solo acepta `asc` o `desc`, default a `desc`
3. **Fallback seguro:** Si se envía un campo no permitido, usa ordenamiento por defecto (`created_at desc`)

---

## Comportamiento de los Endpoints

### Endpoint: GET /api/admin/users

#### Ejemplos de Uso

**1. Sin parámetros (comportamiento por defecto):**
```bash
GET /api/admin/users
```
Retorna: 40 usuarios ordenados por `created_at DESC`

**2. Ordenar por email ascendente:**
```bash
GET /api/admin/users?sort_by=email&order=asc
```

**3. Ordenar por cantidad de sesiones descendente:**
```bash
GET /api/admin/users?sort_by=sessions_count&order=desc
```

**4. Ordenar por grupo con filtros combinados:**
```bash
GET /api/admin/users?sort_by=group&order=asc&profile=student&search=maria
```

**5. Paginación personalizada:**
```bash
GET /api/admin/users?per_page=100&page=2&sort_by=name&order=asc
```

---

## Mejoras de Rendimiento

### Antes

```php
$users = $query->withCount('gameSessions')
    ->orderBy('created_at', 'desc')
    ->paginate(15);
```

**Problemas:**
- Ordenamiento fijo (solo por `created_at`)
- Paginación pequeña (15 registros) → más llamadas al servidor
- No flexible para necesidades del administrador

### Después

```php
$query->withCount('gameSessions');

if (isset($allowedSortFields[$sortBy])) {
    $dbField = $allowedSortFields[$sortBy];
    $query->orderBy($dbField, $order);
} else {
    $query->orderBy('created_at', 'desc');
}

$users = $query->paginate($perPage);
```

**Beneficios:**
- ✅ Ordenamiento dinámico por 5 campos diferentes
- ✅ Paginación aumentada a 40 registros → menos llamadas
- ✅ Dirección de orden configurable (ASC/DESC)
- ✅ Seguridad: validación de campos permitidos
- ✅ Flexibilidad: combinable con filtros existentes

---

## Compatibilidad con Versiones Anteriores

✅ **Totalmente compatible**

Los parámetros `sort_by` y `order` son opcionales. Si no se envían, el sistema usa valores por defecto:

```php
$sortBy = $request->input('sort_by', 'created_at');  // Default
$order = $request->input('order', 'desc');            // Default
$perPage = $request->input('per_page', 40);           // Default aumentado
```

---

## Archivos Modificados

### Backend
- ✅ `app/Http/Controllers/AdminController.php`
  - Método `listUsers()` (líneas 49-119)
  - Agregado mapeo de campos ordenables
  - Agregada lógica de ordenamiento dinámico
  - Aumentado `per_page` default de 15 a 40

---

## Conclusión

✅ **Implementación exitosa de ordenamiento dinámico**
✅ **Paginación mejorada de 15 a 40 registros**
✅ **Seguridad validada con whitelist de campos**
✅ **Compatible con versiones anteriores**
✅ **Listo para integración con frontend**

Las mejoras permiten a los administradores organizar eficientemente grandes listas de usuarios, reduciendo la cantidad de páginas necesarias y permitiendo ordenamiento personalizado según sus necesidades específicas.

---

## Referencias

- **Archivo modificado:** `app/Http/Controllers/AdminController.php`
- **Método:** `listUsers()`
- **Líneas:** 49-119
- **Laravel Docs:** [Query Builder - Ordering](https://laravel.com/docs/11.x/queries#ordering)
- **Laravel Docs:** [Pagination](https://laravel.com/docs/11.x/pagination)
