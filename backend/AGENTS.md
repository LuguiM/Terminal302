# AGENTS.md — Backend (Terminal302)

## Reglas generales (Backend)

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

## Patrón general de respuestas JSON

Las respuestas deben mantener un formato consistente con los controladores ya existentes del proyecto.

### Respuesta exitosa simple

```json
{
  "message": "Operación realizada correctamente."
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

## Respuestas paginadas estandarizadas

Para los endpoints `index` de los controladores se debe usar el helper `App\Support\ApiResponse` con el fin de mantener una estructura uniforme en las respuestas paginadas.

### Uso esperado en controladores

```php
return ApiResponse::paginated(
    $paginator,
    'nombre_del_recurso',
    NombreResource::class
);
```

### Reglas para paginación

- Todos los endpoints `index` que usen paginación deben usar `ApiResponse::paginated`.
- No devolver directamente el resultado de `paginate()`.
- No construir manualmente el bloque `pagination` dentro del controlador.
- El nombre de `$dataKey` debe ser descriptivo y en plural.
- Los datos deben transformarse siempre usando el Resource correspondiente.
- Los endpoints de listado deben recibir `per_page` cuando aplique.

## Resources

Todo endpoint que devuelva modelos debe usar API Resource.

Convención:

- Nombre singular + `Resource` (por ejemplo `UserResource`).

## Form Requests

Toda creación o actualización debe usar Form Request (`Store...Request`, `Update...Request`).

## Estados

El sistema usa tabla `estados`.

Reglas generales:

- Al crear registros, no pedir `estado_id`, salvo que se indique explícitamente.
- El estado inicial normalmente debe ser `activo`.
- El estado debe obtenerse desde la tabla `estados` por nombre.
- Para activar/desactivar, usar endpoint específico `toggle-status`.

## Eliminación

- Por defecto, no eliminar físicamente registros; usar cambio de estado activo/inactivo.
- Si se permite eliminación física, validar reglas de negocio y pertenencia del recurso.

## Endpoints individuales

- No crear endpoint `show` salvo que se solicite explícitamente.

## Seguridad y roles

Roles iniciales:

- `administrador`, `empresario`, `vendedor`, `validador`

Reglas:

- Los endpoints deben estar protegidos con Sanctum cuando aplique.
- Validar permisos según rol del usuario autenticado.

## Archivos y AWS

- Preparar integración para AWS (usar `Storage` y variables de entorno).
- No hardcodear rutas físicas.
- En local usar disco local; en producción usar S3 si se solicita.

## Convenciones de nombres

- Controladores: nombres claros; prefix `Admin` para funcionalidades administrativas.
- Resources: Nombre singular + `Resource`.
- Requests: `Store...Request`, `Update...Request`.
- Modelos: nombres en singular y relaciones Eloquent claras.

## Antes de modificar código (Checklist)

1. Revisar la estructura actual.
2. Indicar qué archivos se crearán o modificarán.
3. Mantener los patrones existentes del proyecto.
4. Respetar este archivo `backend/AGENTS.md`.
5. No implementar módulos no solicitados.
