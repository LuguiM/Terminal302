# Terminal302

Terminal302 es una aplicación web para digitalizar la gestión operativa de una terminal de buses. El proyecto incluye una API REST en Laravel, dos aplicaciones Vue y una base de datos PostgreSQL, todo preparado para ejecutarse con Docker Compose.

## Tecnologías principales

- Backend: Laravel 13, PHP 8.3 y Laravel Sanctum.
- Frontend privado: Vue 3, Vite y Vuetify.
- Frontend público: Vue 3, Vite y Vuetify.
- Base de datos: PostgreSQL 16.
- Entorno local: Docker Compose.

## Estructura

```text
Terminal302/
|-- backend/                 API REST
|-- frontend/                Aplicación para usuarios autenticados
|-- public-frontend/         Consulta y validación pública de tickets
|-- lambda/                  Funciones preparadas para AWS
|-- infrastructure/          Infraestructura preparada para AWS
|-- docs/                    Documentación del proyecto
|-- docker-compose.yml
`-- README.md
```

## Requisitos

Para el flujo recomendado solo necesitas:

- Git.
- Docker Desktop o Docker Engine con Docker Compose.

No necesitas instalar PHP, Composer, Node.js ni PostgreSQL en tu máquina. Si decides ejecutar los servicios sin Docker, necesitarás PHP 8.3+, Composer, Node.js 22+ y PostgreSQL 16; ese flujo no se cubre en esta guía.

## Instalación con Docker

### 1. Clonar y entrar al proyecto

```bash
git clone <URL_DEL_REPOSITORIO>
cd Terminal302
```

### 2. Crear los archivos de entorno

En PowerShell:

```powershell
Copy-Item .env.example .env
Copy-Item backend\.env.example backend\.env
Copy-Item frontend\.env.example frontend\.env
Copy-Item public-frontend\.env.example public-frontend\.env
```

En Bash:

```bash
cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
cp public-frontend/.env.example public-frontend/.env
```

Los valores predeterminados sirven para desarrollo local. Docker Compose configura internamente la conexión de Laravel a PostgreSQL y las URL de ambos frontends hacia la API.

### 3. Levantar todos los servicios

```bash
docker compose --profile frontend --profile public up -d --build
```

Este comando inicia PostgreSQL, el backend, el frontend privado y el frontend público.

### 4. Preparar Laravel y la base de datos

```bash
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --seed
```

El seeder crea un administrador inicial usando `INITIAL_ADMIN_EMAIL` de `backend/.env`. La contraseña temporal se muestra una sola vez en la consola y debe cambiarse en el primer inicio de sesión.

## Servicios disponibles

| Servicio | URL |
|---|---|
| Frontend privado | <http://localhost:5173> |
| Frontend público | <http://localhost:5174> |
| API | <http://localhost:8302/api> |
| Swagger UI | <http://localhost:8302/docs/api> |
| OpenAPI YAML | <http://localhost:8302/docs/api/openapi.yaml> |

Los puertos se pueden modificar en el archivo `.env` de la raíz.

## Comandos cotidianos

### Controlar los servicios

```bash
# Ver el estado
docker compose ps

# Ver logs del backend
docker compose logs -f backend

# Detener los servicios
docker compose --profile frontend --profile public down
```

Para iniciar solo una parte del proyecto:

```bash
# Backend y PostgreSQL
docker compose up -d

# Backend, PostgreSQL y frontend privado
docker compose --profile frontend up -d

# Backend, PostgreSQL y frontend público
docker compose --profile public up -d
```

El archivo `docker-compose.override.yml` se aplica automáticamente en desarrollo y monta el código del backend dentro del contenedor. Los cambios normales de PHP no requieren reconstruir la imagen.

### Aplicar cambios de configuración o dependencias

```bash
# Reiniciar después de cambiar backend/.env
docker compose restart backend

# Recrear el backend si las variables no se actualizaron
docker compose up -d --force-recreate backend

# Reconstruir después de cambiar el Dockerfile o dependencias del sistema
docker compose up -d --build backend

# Restaurar dependencias PHP si falta backend/vendor
docker compose exec backend composer install
```

## Base de datos y pruebas

```bash
# Aplicar migraciones pendientes sin borrar datos
docker compose exec backend php artisan migrate

# Ejecutar seeders
docker compose exec backend php artisan db:seed

# Ejecutar pruebas del backend
docker compose exec backend php artisan test
```

Para reiniciar completamente la base local:

```bash
docker compose exec backend php artisan migrate:fresh --seed
```

> **Advertencia:** `migrate:fresh --seed` elimina todas las tablas y datos de la base configurada antes de ejecutar nuevamente las migraciones y seeders.

Los tests utilizan la base separada `terminal302_testing`, configurada en `backend/phpunit.xml`. En una instalación nueva se crea automáticamente. Si el volumen de PostgreSQL existía antes de incorporar esa base, créala una sola vez:

```bash
docker compose exec postgres createdb -U terminal302 terminal302_testing
```

### Conectar una herramienta externa a PostgreSQL

Con la configuración predeterminada:

```text
Host: localhost
Port: 15432
Database: terminal302
User: terminal302
Password: change_me_locally
```

Dentro de Docker, el backend usa `postgres:5432` en lugar de `localhost:15432`.

## Correos y tickets digitales en local

Por defecto, Laravel usa `MAIL_MAILER=log`; los correos se registran sin enviarse mediante SMTP. Para probar una entrega digital pendiente:

```bash
docker compose exec backend php artisan tickets:process-digital-deliveries
```

La configuración de Mailpit, Mailtrap y el comportamiento detallado de las entregas se explican en la [referencia funcional](docs/REFERENCIA_FUNCIONAL.md#tickets-digitales-y-correo-local).

## Solución de problemas

### El backend indica que falta `vendor`

```bash
docker compose exec backend composer install
```

### Un cambio de `backend/.env` no se refleja

```bash
docker compose up -d --force-recreate backend
```

### Un puerto ya está ocupado

Cambia `BACKEND_PORT`, `FRONTEND_PORT`, `PUBLIC_FRONTEND_PORT` o `POSTGRES_PORT` en `.env` y vuelve a levantar los servicios.

### La base de pruebas no existe

Ejecuta una vez:

```bash
docker compose exec postgres createdb -U terminal302 terminal302_testing
```

## Documentación adicional

- [Referencia funcional y endpoints](docs/REFERENCIA_FUNCIONAL.md)
- [Swagger UI](http://localhost:8302/docs/api)
- [Especificación OpenAPI](http://localhost:8302/docs/api/openapi.yaml)

La integración real con AWS está prevista para fases posteriores. Las carpetas `lambda/`, `infrastructure/` y `docs/` sirven como base para esa evolución.
