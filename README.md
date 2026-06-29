# Terminal302

Terminal302 es un proyecto academico para digitalizar la gestion operativa de una terminal de buses. La primera etapa deja lista una base con Laravel API REST, Vue 3 con Vite, PostgreSQL y Docker Compose.

## Estructura

```text
Terminal302/
├── backend/
├── frontend/
├── lambda/
│   └── public-ticket-validation/
├── infrastructure/
├── docs/
├── docker-compose.yml
└── README.md
```

## Requisitos

- PHP 8.3+
- Composer
- Node.js 22+
- Docker y Docker Compose

## Configuracion local

1. Copiar variables de entorno:

```bash
cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
```

2. Ajustar credenciales locales en `.env` y `backend/.env`. Para Docker, `backend/.env` debe usar `DB_HOST=postgres` y `DB_PORT=5432`. El puerto publicado de PostgreSQL hacia tu maquina es `15432` por defecto para evitar choques con instalaciones locales.

   Dentro de Docker:

   ```env
   DB_HOST=postgres
   DB_PORT=5432
   ```

   Desde herramientas en tu maquina, como DBeaver, TablePlus o psql local:

   ```text
   Host: localhost
   Port: 15432
   ```

3. Generar la llave de Laravel:

```bash
cd backend
php artisan key:generate
```

4. Instalar dependencias si aun no existen:

```bash
composer install
cd ../frontend
npm install
```

## Levantar con Docker

Desde la raiz del proyecto:

```bash
docker compose up --build
```

Para levantar tambien el frontend Vite:

```bash
docker compose --profile frontend up --build
```

Backend: `http://localhost:8302`

Frontend: `http://localhost:5173`

Swagger UI: `http://localhost:8302/docs/api`

OpenAPI YAML: `http://localhost:8302/docs/api/openapi.yaml`

## Flujo Docker para desarrollo

El repositorio incluye `docker-compose.override.yml`. Docker Compose lo carga automaticamente en desarrollo local y monta `./backend` dentro del contenedor. Esto permite que los cambios en controllers, routes, requests, resources, vistas Blade, Swagger y otros archivos PHP se reflejen sin reconstruir la imagen.

El volumen `backend_vendor` mantiene las dependencias Composer dentro del contenedor para que el montaje local de `./backend` no borre `vendor`.

### Comandos frecuentes

Levantar backend y PostgreSQL:

```bash
docker compose up -d
```

Levantar tambien el frontend:

```bash
docker compose --profile frontend up -d
```

Ver logs del backend:

```bash
docker compose logs -f backend
```

Reiniciar el backend despues de cambios en `backend/.env`:

```bash
docker compose restart backend
```

Recrear el backend si el cambio de `.env` no se refleja:

```bash
docker compose up -d --force-recreate backend
```

Reconstruir cuando cambie `Dockerfile`, extensiones PHP, dependencias del sistema o instalacion de Composer:

```bash
docker compose up -d --build backend
```

Instalar dependencias Composer dentro del volumen `backend_vendor` si queda vacio o si aparece un error de `vendor` faltante:

```bash
docker compose exec backend composer install
```

Ejecutar Artisan dentro del backend:

```bash
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan test
```

Ejecutar sin el override de desarrollo, usando solo la imagen autocontenida parecida a produccion:

```bash
docker compose -f docker-compose.yml up -d --build
```

## Migraciones y seeders

Con los contenedores arriba:

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

El seeder genera un administrador inicial con email configurable mediante `INITIAL_ADMIN_EMAIL`. La contrasena temporal se muestra en consola una sola vez y el usuario debe cambiarla en su primer inicio de sesion.

## API inicial

Base URL: `http://localhost:8302/api`

- `POST /login`
- `POST /logout` con Bearer token
- `GET /user` con Bearer token
- `POST /change-initial-password` con Bearer token

## Gestion de usuarios administradores

Rutas protegidas con Bearer token, rol `administrador` y contrasena inicial ya cambiada:

- `GET /admin/users`
- `POST /admin/users` con `name`, `email`, `role_id`
- `GET /admin/users/{id}`
- `PUT /admin/users/{id}` con `name`, `email`, `role_id`
- `PATCH /admin/users/{id}/reset-password`
- `PATCH /admin/users/{id}/toggle-status`

Crear usuario no recibe contrasena ni `estado_id`. El sistema asigna automaticamente el estado activo, genera una contrasena temporal, la guarda encriptada, marca `must_change_password=true` y envia un correo con las credenciales iniciales.

El estado de un usuario se cambia solo con `PATCH /admin/users/{id}/toggle-status`, sin body. Si el usuario esta activo pasa a inactivo/desactivado; si esta inactivo/desactivado vuelve a activo. Un administrador no puede desactivarse a si mismo mediante este endpoint.

Para restablecer una contrasena, usa `PATCH /admin/users/{id}/reset-password`, sin body. El sistema genera una nueva contrasena temporal, la guarda encriptada, marca `must_change_password=true` y envia el correo de restablecimiento. La contrasena temporal no se devuelve en JSON.

## Operadores de transporte

Rutas para empresarios, protegidas con Bearer token, rol `empresario` y contrasena inicial ya cambiada:

- `GET /operador/me`
- `POST /operador`
- `PUT /operador/{id}`

El empresario puede registrar un solo operador. Al crear el operador no se envia `estado_id`, `user_id` ni `motivo_desactivacion`; el sistema lo asocia automaticamente al usuario autenticado y lo deja activo.

Campos base para crear o editar operador:

- `tipo_operador_id`
- `nombre`
- `documento` opcional
- `telefono`
- `correo`
- `direccion`

Si el tipo de operador es `empresa`, tambien son obligatorios `razon_social` y `representante_legal`. Si el tipo es `persona`, esos campos pueden ir como `null` o no enviarse.

Rutas para administradores:

- `GET /admin/operadores`
- `GET /admin/operadores/{id}`
- `PATCH /admin/operadores/{id}/toggle-status`

Para desactivar un operador activo, el administrador debe enviar un motivo:

```json
{
  "motivo_desactivacion": "Documentacion vencida"
}
```

Al reactivar un operador desactivado, el mismo endpoint se ejecuta sin body y el sistema limpia `motivo_desactivacion`.

Un operador desactivado no desactiva el usuario empresario. El empresario puede iniciar sesion, pero sus endpoints de operador y futuras acciones operativas responden `403` hasta que el operador vuelva a estar activo.

## Rutas

Rutas administrativas protegidas con Bearer token, rol `administrador` y contrasena inicial ya cambiada:

- `GET /admin/rutas`
- `POST /admin/rutas`
- `PUT /admin/rutas/{id}`
- `PATCH /admin/rutas/{id}/toggle-status`
- `DELETE /admin/rutas/{id}`

Campos para crear o editar ruta:

- `ruta`
- `denominacion`
- `tarifa`

Al crear una ruta no se envia `estado_id`; el sistema asigna automaticamente el estado activo. El endpoint `PATCH /admin/rutas/{id}/toggle-status` alterna entre activo e inactivo/desactivado sin body.

Para este catalogo, `DELETE /admin/rutas/{id}` elimina fisicamente la ruta de la base de datos. No existe endpoint `GET /admin/rutas/{id}`.

## Rutas del operador

Rutas para empresarios, protegidas con Bearer token, rol `empresario`, contrasena inicial ya cambiada y operador activo:

- `GET /operador/rutas`
- `POST /operador/rutas`
- `PATCH /operador/rutas/{id}/toggle-status`
- `DELETE /operador/rutas/{id}`

El empresario solo gestiona las rutas de su propio operador. Para asignar una ruta se envia:

```json
{
  "ruta_id": 1
}
```

El sistema obtiene automaticamente el operador desde el usuario autenticado y asigna estado activo. No se debe enviar `operador_id` ni `estado_id`.

Solo se pueden asignar rutas activas del catalogo general. No se permite duplicar la misma combinacion operador + ruta.

`GET /operador/rutas` devuelve asignaciones activas e inactivas para gestionarlas. Los listados operativos futuros deberan filtrar solo asignaciones activas.

Si el empresario se equivoca, debe eliminar la asignacion con `DELETE /operador/rutas/{id}` y crear una nueva. No existe endpoint de detalle ni endpoint de edicion para esta asignacion.

## Buses del operador

Rutas para empresarios, protegidas con Bearer token, rol `empresario`, contrasena inicial ya cambiada y operador activo:

- `GET /operador/buses`
- `POST /operador/buses`
- `PUT /operador/buses/{id}`
- `PATCH /operador/buses/{id}/toggle-status`

No existe endpoint de detalle ni eliminacion para buses en esta etapa.

Campos para crear o editar bus:

- `ruta_id`
- `placa`
- `marca`
- `nombre_unidad` opcional
- `capacidad`
- `tipo_bus_id`

El sistema obtiene automaticamente el operador desde el usuario autenticado y asigna estado activo al crear. No se debe enviar `operador_id` ni `estado_id`.

La ruta seleccionada debe existir, estar activa en el catalogo general y estar asignada activamente al operador en `operador_rutas`. La placa es unica globalmente y la capacidad debe ser mayor a 0.

`GET /operador/buses` devuelve una lista paginada y permite filtros opcionales:

- `ruta_id`
- `estado_id`
- `search` por placa, marca o nombre de unidad

El endpoint `PATCH /operador/buses/{id}/toggle-status` alterna entre activo e inactivo/desactivado sin recibir body.

Ejemplo de login:

```bash
curl -X POST http://localhost:8302/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@terminal302.local","password":"TEMPORAL"}'
```

## Correos en local

Por defecto el proyecto usa `MAIL_MAILER=log`. Para probar envio SMTP con Mailpit instalado localmente:

```env
MAIL_MAILER=smtp
MAIL_HOST=host.docker.internal
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=no-reply@terminal302.local
MAIL_FROM_NAME=Terminal302
```

Si ejecutas Laravel fuera de Docker, usa `MAIL_HOST=127.0.0.1`. La interfaz web de Mailpit normalmente queda en `http://localhost:8025`.

Para Mailtrap, configura en `backend/.env` los valores SMTP que entrega Mailtrap:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=TU_USUARIO_MAILTRAP
MAIL_PASSWORD=TU_PASSWORD_MAILTRAP
```

## Alcance actual

Esta etapa no implementa horarios, tickets, QR, validacion publica ni integracion real con AWS. Las carpetas `lambda/`, `infrastructure/` y `docs/` quedan preparadas para crecer en fases posteriores.
