# AGENTS.md — Terminal302

## Contexto general

Terminal302 es una aplicación web para digitalizar la gestión operativa de una terminal de buses.

Stack principal:

- Backend: Laravel API REST
- Base de datos: PostgreSQL
- Frontend: Vue 3
- Autenticación: Laravel Sanctum
- Contenedores: Docker / Docker Compose
- Futuro despliegue: AWS

El backend debe funcionar como API REST. No usar Blade para vistas del sistema, excepto para correos si Laravel Mail lo requiere.

---

## Reglas generales

- Mantener el código limpio, organizado y coherente con los patrones existentes.
- Usar migraciones para cambios de base de datos.
- No modificar migraciones ya ejecutadas; crear nuevas migraciones para cambios futuros.
- Usar Form Requests para validaciones.
- Usar API Resources para respuestas JSON.
- Usar controladores API.
- Usar middleware de autenticación con Sanctum.
- Usar middleware o validaciones por rol.
- No hardcodear credenciales, rutas absolutas ni configuraciones sensibles.
- Usar variables de entorno.
- No implementar módulos no solicitados.
- Antes de modificar código, revisar la estructura actual del proyecto.
- Antes de implementar, indicar qué archivos se crearán o modificarán.

---

## Patrón general de respuestas JSON

Las respuestas deben mantener un formato consistente con los controladores ya existentes del proyecto.

### Respuesta exitosa simple

```json
{
  "message": "Operación realizada correctamente.",
}
```

### Respuesta de error

```json
{
  "message": "No se pudo completar la operación.",
  "errors": {}
}
```

Notas:

- Mezclar varios estilos de respuesta en un mismo módulo siempre y cuando sea necesario.
- Para listados paginados, usar siempre `App\Support\ApiResponse`.

---

## Respuestas paginadas estandarizadas

Para los endpoints `index` de los controladores se debe usar el helper `App\Support\ApiResponse` con el fin de mantener una estructura uniforme en las respuestas paginadas.

Archivo utilizado:

```php
App\Support\ApiResponse
```

Estructura del helper:

```php
<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * @param  class-string<JsonResource>  $resourceClass
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $dataKey,
        string $resourceClass,
    ): JsonResponse {
        return response()->json([
            $dataKey => $resourceClass::collection($paginator->getCollection()),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
```

### Uso esperado en controladores

En los métodos `index`, no construir manualmente la respuesta paginada. Usar siempre:

```php
return ApiResponse::paginated(
    $paginator,
    'nombre_del_recurso',
    NombreResource::class
);
```

Ejemplo:

```php
return ApiResponse::paginated(
    $usuarios,
    'usuarios',
    UserResource::class
);
```

### Reglas para paginación

- Todos los endpoints `index` que usen paginación deben usar `ApiResponse::paginated`.
- No devolver directamente el resultado de `paginate()`.
- No construir manualmente el bloque `pagination` dentro del controlador.
- El nombre de `$dataKey` debe ser descriptivo y en plural, por ejemplo:
  - `usuarios`
  - `operadores`
  - `rutas`
  - `operador_rutas`
- Los datos deben transformarse siempre usando el Resource correspondiente.
- Los endpoints de listado deben recibir `per_page` cuando aplique.
- Se puede usar `search` cuando el módulo lo requiera.
- Se puede usar `estado_id` como filtro cuando el módulo lo requiera.
- No devolver colecciones grandes sin paginación si el recurso puede crecer.

Formato esperado:

```json
{
  "usuarios": [],
  "pagination": {
    "page": 1,
    "per_page": 10,
    "total": 50,
    "last_page": 5
  }
}
```

---

## Resources

Todo endpoint que devuelva modelos debe usar API Resource.

Si puede devolver modelos Eloquent directamente desde los controladores si aplica el caso.

Convención:

- Nombre singular + `Resource`
- Ejemplos:
  - `UserResource`
  - `OperadorResource`
  - `RutaResource`
  - `OperadorRutaResource`

---

## Form Requests

Toda creación o actualización debe usar Form Request.

Convención:

- `Store...Request`
- `Update...Request`

Las reglas de validación deben estar en los Form Requests y no directamente en el controlador, salvo validaciones de negocio específicas.

---

## Estados

El sistema usa tabla `estados`.

Reglas generales:

- Al crear registros, no pedir `estado_id`, salvo que se indique explícitamente.
- El estado inicial normalmente debe ser `activo`.
- El estado debe obtenerse desde la tabla `estados` por nombre.
- Para activar/desactivar, usar endpoint específico `toggle-status`.
- El endpoint `toggle-status` no debe recibir `estado_id` en el body.
- Solo debe recibir el ID del recurso por parámetro de ruta.
- El endpoint debe alternar automáticamente entre `activo` e `inactivo`.

---

## Eliminación

Por defecto, no eliminar físicamente registros, salvo que el prompt indique explícitamente que sí se permite.

Cuando no se permita eliminación física:

- Usar cambio de estado activo/inactivo.

Cuando se permita eliminación física:

- Validar reglas de negocio antes de eliminar.
- Validar pertenencia del recurso al usuario autenticado si aplica.

---

## Endpoints individuales

No crear endpoint `show` salvo que se solicite explícitamente.

Si el módulo no necesita detalle individual:

- No crear método `show`.
- No registrar ruta `GET /recurso/{id}`.

---

## Seguridad y roles

Roles iniciales del sistema:

- `administrador`
- `empresario`
- `vendedor`
- `validador`

Reglas:

- Los endpoints deben estar protegidos con Sanctum cuando aplique.
- Las acciones deben validarse según el rol del usuario autenticado.
- No asumir permisos sin que el prompt lo especifique.
- No implementar accesos globales si el recurso debe pertenecer al usuario autenticado.

---

## Archivos y AWS

El desarrollo debe quedar preparado para AWS.

Reglas:

- No guardar rutas físicas hardcodeadas para archivos.
- Usar `Storage` de Laravel para archivos.
- En local se puede usar disco local.
- En AWS se usará S3 cambiando variables de entorno.
- La validación pública de tickets debe diseñarse para poder migrar posteriormente a Lambda.
- No implementar integración real con AWS salvo que se solicite explícitamente.

---

## Convenciones de nombres

Controladores:

- Usar nombres claros según el contexto.
- Si es funcionalidad administrativa, usar prefijo `Admin`.
- Ejemplos:
  - `AdminUserController`
  - `AdminRutaController`
  - `OperadorController`
  - `OperadorRutaController`

Resources:

- Nombre singular + `Resource`.

Requests:

- `Store...Request`
- `Update...Request`.

Modelos:

- Nombres en singular.
- Relaciones Eloquent claras y consistentes.

---

## Antes de modificar código

Antes de implementar cambios, el agente debe:

1. Revisar la estructura actual.
2. Indicar qué archivos creará o modificará.
3. Mantener los patrones existentes del proyecto.
4. Respetar este archivo `AGENTS.md`.
5. No implementar módulos no solicitados.
