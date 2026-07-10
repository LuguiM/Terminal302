# Referencia funcional de Terminal302

Este documento resume los módulos, endpoints y reglas de negocio actuales. Para instalar y ejecutar el proyecto, consulta el [README principal](../README.md).

## Convenciones de la API

- URL base local: `http://localhost:8302/api`.
- Las rutas privadas requieren un Bearer token emitido por Laravel Sanctum.
- Las operaciones protegidas requieren que el usuario haya cambiado su contraseña inicial.
- Los endpoints de empresario que administran recursos operativos también requieren un operador activo.
- La documentación navegable está disponible en [Swagger UI](http://localhost:8302/docs/api).

## Autenticación

```text
POST /login
POST /logout
GET  /user
POST /change-initial-password
```

`logout`, `user` y `change-initial-password` requieren Bearer token. El login devuelve `requires_operator_registration=true` cuando el usuario tiene rol `empresario` y todavía no ha registrado un operador.

Ejemplo de login:

```bash
curl -X POST http://localhost:8302/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@terminal302.local","password":"TEMPORAL"}'
```

## Usuarios administradores

Rutas para el rol `administrador`:

```text
GET   /admin/users
POST  /admin/users
GET   /admin/users/{id}
PUT   /admin/users/{id}
PATCH /admin/users/{id}/reset-password
PATCH /admin/users/{id}/toggle-status
```

Al crear un usuario se envían `name`, `email` y `role_id`. El sistema asigna el estado activo, genera y cifra una contraseña temporal, establece `must_change_password=true` y envía las credenciales por correo.

El cambio de estado y el restablecimiento de contraseña no reciben body. Un administrador no puede desactivarse a sí mismo. La contraseña temporal generada durante un restablecimiento no se devuelve en el JSON.

## Operadores de transporte

### Empresario

```text
GET  /operador/me
POST /operador
PUT  /operador/{id}
```

Cada empresario puede registrar un único operador. El backend obtiene el usuario autenticado y asigna el estado activo; no se envían `user_id`, `estado_id` ni `motivo_desactivacion`.

Campos comunes:

- `tipo_operador_id`
- `nombre_comercial`

Para una empresa también son obligatorios `razon_social`, `representante_legal` y `nit`, con formato `0614-290695-101-3`. `direccion`, `telefono` y `correo_administrativo` son opcionales.

Para una persona son obligatorios `dui`, con formato `12345678-9`, y `telefono`. `telefono_opcional` es opcional.

El backend no acepta los campos antiguos `nombre`, `documento` ni `correo`.

### Administrador

```text
GET   /admin/operadores
GET   /admin/operadores/{id}
PATCH /admin/operadores/{id}/toggle-status
```

Para desactivar un operador se requiere:

```json
{
  "motivo_desactivacion": "Documentación vencida"
}
```

Al reactivarlo, se llama al mismo endpoint sin body y se limpia el motivo. Desactivar un operador no desactiva al usuario empresario, pero sus operaciones empresariales responden `403` hasta que el operador vuelva a estar activo.

## Catálogo de rutas

Rutas administrativas:

```text
GET    /admin/rutas
POST   /admin/rutas
PUT    /admin/rutas/{id}
PATCH  /admin/rutas/{id}/toggle-status
DELETE /admin/rutas/{id}
```

Los campos son `ruta`, `denominacion` y `tarifa`. El estado activo se asigna automáticamente. `toggle-status` no recibe body y `DELETE` elimina físicamente la ruta. No existe un endpoint administrativo de detalle.

## Rutas del operador

```text
GET    /operador/rutas
POST   /operador/rutas
PATCH  /operador/rutas/{id}/toggle-status
DELETE /operador/rutas/{id}
```

Para asignar una ruta:

```json
{
  "ruta_id": 1
}
```

El operador se obtiene del usuario autenticado; no se envían `operador_id` ni `estado_id`. Solo se asignan rutas activas y no se permiten duplicados de operador y ruta. El listado incluye asignaciones activas e inactivas.

No existe edición ni detalle. Para corregir una asignación se elimina y se crea nuevamente.

## Buses del operador

```text
GET   /operador/buses
POST  /operador/buses
PUT   /operador/buses/{id}
PATCH /operador/buses/{id}/toggle-status
```

Campos:

- `ruta_id`
- `placa`
- `marca`
- `nombre_unidad`, opcional
- `capacidad`
- `tipo_bus_id`

El operador y el estado activo se asignan automáticamente. La ruta debe estar activa y asignada activamente al operador. La placa es única globalmente y la capacidad debe ser mayor que cero.

El listado es paginado y acepta `ruta_id`, `estado_id` y `search` por placa, marca o nombre de unidad. No existen endpoints de detalle ni eliminación.

## Horarios

Los horarios representan salidas programadas por ruta, operador, bus, día y hora. `hora_salida` usa el formato `HH:mm`.

### Administrador

```text
GET    /admin/horarios/rutas
GET    /admin/horarios/rutas/{ruta_id}
GET    /admin/horarios
GET    /admin/horarios/rutas/{ruta_id}/operadores
GET    /admin/horarios/buses
POST   /admin/horarios
PUT    /admin/horarios/{id}
PATCH  /admin/horarios/{id}/toggle-status
DELETE /admin/horarios/{id}
```

Ejemplos de consultas:

```text
GET /admin/horarios?ruta_id=1&dia_id=1
GET /admin/horarios/rutas/1/operadores
GET /admin/horarios/buses?ruta_id=1&operador_id=1
```

Campos de creación y edición:

- `ruta_id`
- `operador_id`
- `bus_id`
- `dia_id`
- `hora_salida`
- `sobreventa_permitida`

El operador debe estar activo y tener asignada la ruta. El bus debe estar activo, pertenecer al operador y usar la misma ruta. No se permite duplicar ruta, operador, bus, día y hora. El cambio de estado no recibe body y la eliminación es física.

### Empresario

```text
GET /operador/horarios/rutas
GET /operador/horarios/rutas/{ruta_id}
GET /operador/horarios
```

El empresario solo consulta sus rutas asignadas activamente y sus horarios activos. Puede filtrar horarios con `ruta_id` y `dia_id`. No puede crear, editar, eliminar ni cambiar el estado de horarios.

## Ventas de horarios

`ventas_horarios` controla el ciclo diario de venta de un horario recurrente.

Rutas para `vendedor`:

```text
GET   /vendedor/rutas-disponibles
GET   /vendedor/rutas/{ruta_id}/horarios-disponibles
PATCH /vendedor/ventas-horarios/{id}/cerrar
```

Los horarios disponibles se calculan usando `America/El_Salvador`. Si no existe una venta para el horario y la fecha actual, el sistema la crea con venta abierta, contadores en cero y estado activo.

La respuesta informa capacidad, tickets vendidos, sobreventa, estado de cierre y `puede_vender`. Para cerrar una venta:

```json
{
  "motivo_cierre": "Unidad completa"
}
```

El vendedor autenticado y la fecha de cierre se asignan automáticamente. No existen operaciones manuales para crear, editar, eliminar o consultar individualmente una `venta_horario`.

## Tickets digitales y correo local

Una venta con tipo de envío `digital` crea los tickets, genera el QR y la imagen PNG, y registra un evento pendiente en:

```text
ticket-events/pending/{codigo_ticket}.json
```

Para procesar los eventos localmente:

```bash
docker compose exec backend php artisan tickets:process-digital-deliveries
```

Para limitar la cantidad:

```bash
docker compose exec backend php artisan tickets:process-digital-deliveries --limit=10
```

El comando envía el ticket PNG al correo de destino y mueve el evento a `ticket-events/completed`. Si falla, lo mueve a `ticket-events/failed` y guarda el error. El ticket registra `processed_at` o `processing_error` según el resultado.

### Mailpit

La configuración predeterminada usa `MAIL_MAILER=log`. Para probar SMTP con Mailpit instalado en la máquina anfitriona, configura `backend/.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=host.docker.internal
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=no-reply@terminal302.local
MAIL_FROM_NAME=Terminal302
```

La interfaz de Mailpit normalmente queda en <http://localhost:8025>. Si Laravel se ejecuta fuera de Docker, usa `MAIL_HOST=127.0.0.1`.

### Mailtrap

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=TU_USUARIO_MAILTRAP
MAIL_PASSWORD=TU_PASSWORD_MAILTRAP
```

## Alcance actual

El proyecto incluye autenticación, administración de usuarios, operadores, rutas, buses, horarios, venta y validación de tickets, y entregas digitales. La integración real con AWS está prevista para fases posteriores.
