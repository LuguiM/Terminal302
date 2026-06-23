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

2. Ajustar credenciales locales en `.env` y `backend/.env`. Para Docker, `backend/.env` debe usar `DB_HOST=postgres`. El puerto publicado de PostgreSQL es `15432` por defecto para evitar choques con instalaciones locales.

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

Ejemplo de login:

```bash
curl -X POST http://localhost:8302/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@terminal302.local","password":"TEMPORAL"}'
```

## Alcance actual

Esta etapa no implementa rutas, buses, horarios, tickets, QR, validacion publica ni integracion real con AWS. Las carpetas `lambda/`, `infrastructure/` y `docs/` quedan preparadas para crecer en fases posteriores.
