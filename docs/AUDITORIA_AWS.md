# Auditoria tecnica para AWS de Terminal302

Fecha de revision: 2026-08-19.

## 1. Arquitectura detectada

El repositorio contiene una API Laravel 13 sobre PHP 8.3, un frontend privado Vue 3/Vite/Vuetify, un frontend publico Vue 3/Vite/Vuetify, PostgreSQL 16 en Docker Compose y una funcion Python 3.13 administrada con AWS SAM. La autenticacion utiliza tokens Bearer de Laravel Sanctum; no usa cookies para el frontend.

El desarrollo local se compone de `postgres`, `backend`, `frontend` y `public-frontend`. El Dockerfile original del backend ejecuta `php artisan serve`; los frontends ejecutan `npm run dev`. Esos archivos se conservan exclusivamente para desarrollo.

Inventario relevante:

- 23 migraciones y 4 seeders (incluido `DatabaseSeeder`).
- 24 archivos de pruebas backend.
- Almacenamiento mediante `Storage`, local por defecto; plantillas, QR, tickets finales y eventos digitales comparten el disco configurado.
- Generacion QR con `endroid/qr-code` y composicion PNG mediante GD.
- Entregas digitales procesadas por el comando manual `tickets:process-digital-deliveries`.
- No hay clases `Job`, implementaciones `ShouldQueue`, workers obligatorios ni tareas registradas en Laravel Scheduler.
- Lambda existente: validacion publica de estado/fecha de ticket. Laravel conserva fallback local.
- SAM existente en `infrastructure/template.yaml`; no existia IaC para la aplicacion completa.

Versiones detectadas:

| Componente | Version/configuracion |
|---|---|
| PHP | 8.3 (plataforma Composer 8.3.31) |
| Laravel | 13.x |
| Node de contenedores | 22 Alpine |
| Vue | 3.5.x |
| Vite | 8.x |
| PostgreSQL | 16 Alpine en local; 16 en RDS |
| Lambda | Python 3.13, x86_64 |

## 2. Problemas encontrados

### Criticos

- Existia una contrasena fija del administrador inicial versionada en `DatabaseSeeder.php`, y el usuario quedaba sin cambio obligatorio. Debe considerarse comprometida.
- Los contenedores existentes usaban servidores de desarrollo (`artisan serve` y Vite dev server), no adecuados para produccion.
- El filesystem local era el valor predeterminado. En Fargate, QR, plantillas, tickets y eventos se perderian al reemplazar una tarea.
- El adaptador S3 de Flysystem no estaba instalado.
- No existia infraestructura para VPC, ECS, RDS, ALB, WAF, ECR, IAM, Secrets Manager o CloudWatch.

### Importantes

- No habia CORS publicado/configurable y Laravel no declaraba confianza explicita en los headers del ALB.
- `Storage::url()` generaba enlaces no utilizables si el bucket S3 era privado.
- Los logs Laravel predeterminados iban a un archivo del contenedor.
- El procesador de entrega digital es sincrono/manual. No existe todavia la Lambda que consuma eventos S3.
- El correo local usa `MAIL_MAILER=log`; recuperacion de contrasena y entrega digital no enviaran mensajes hasta configurar SMTP/SES.
- La Lambda usaba un secreto directamente en variables de entorno. Ahora puede obtenerlo de Secrets Manager una vez por entorno de ejecucion.
- El OpenAPI conserva un servidor localhost como referencia de desarrollo; no afecta el runtime, pero debe parametrizarse/generarse en una mejora futura.

### Mejoras

- No hay pruebas frontend generales ni lint configurado; el frontend privado solo ofrece una prueba de validaciones.
- La arquitectura minima usa una sola NAT Gateway y RDS Single-AZ para limitar costo; no ofrece alta disponibilidad completa.
- Las alarmas CloudWatch no tienen destino SNS porque no se definio un canal de operaciones.
- No existe CI/CD; el primer despliegue se documenta mediante CLI.

## 3. Cambios realizados

- `backend/Dockerfile.production` y `backend/docker/production/*`: imagen PHP-FPM + Nginx, dependencias sin dev, opcache, logs stderr, health check y comando override para tareas puntuales.
- `frontend/Dockerfile.production`, `frontend/docker/nginx.conf`, `public-frontend/Dockerfile.production` y su Nginx: builds Vite servidos como usuario no-root con fallback SPA.
- `backend/bootstrap/app.php` y `backend/config/cors.php`: forwarded headers del ALB y origenes configurables.
- `backend/app/Support/StorageUrl.php` y consumidores: URL prefirmada cuando el disco es S3 privado.
- `backend/composer.json`/`composer.lock`: adaptador S3; Tinker pasa a desarrollo; CommonMark se actualiza por avisos de seguridad.
- `backend/database/seeders/DatabaseSeeder.php`: elimina la clave fija y exige secreto en produccion.
- `lambda/public-ticket-validation/src/handler.py` y `infrastructure/template.yaml`: secreto desde Secrets Manager con cache, IAM acotado y logs JSON.
- `infrastructure/terraform/*`: IaC reproducible para red, seguridad, ECR, ECS, RDS, S3, ALB/ACM/Route 53, WAF, Lambda/API Gateway, IAM, CloudWatch y Budgets.
- `.env.production.example` y equivalentes por aplicacion: contrato de variables sin valores reales.
- `.gitignore` y `.dockerignore`: state, tfvars, env, caches Python y artefactos excluidos.
- `DEPLOYMENT_AWS.md`, este informe y enlaces de `README.md`: operacion completa y pendientes.
- `frontend/package-lock.json` y `public-frontend/package-lock.json`: correcciones compatibles para Nano ID y PostCSS.

Se retiraron del repositorio dos binarios `__pycache__/*.pyc` generados. Son artefactos recompilables, no codigo fuente ni datos de aplicacion.

## 4. Persistencia y procesos asincronos

En produccion, `FILESYSTEM_DISK=s3` mueve fuera del contenedor:

- `ticket-templates/*`;
- QR y tickets renderizados;
- `ticket-events/pending`, `completed` y `failed`.

Los enlaces de objetos privados pasan a ser URLs prefirmadas de 15 minutos. En local se conserva `Storage::url()`.

El flujo S3 -> Lambda para entregas digitales esta **PENDIENTE DE IMPLEMENTAR**. No se agrega una notificacion S3 porque la unica Lambda existente valida un payload HTTP diferente y conectarla seria funcionalmente incorrecto. Hasta implementar el consumidor, el comando Artisan puede ejecutarse como tarea ECS puntual.

## 5. Seguridad

- No se detectaron Access Key IDs o Secret Access Keys reales versionadas.
- Los `.env` reales siguen ignorados; tambien se ignoran `tfvars`, state y artefactos ZIP.
- RDS y tareas ECS no reciben IP publica. Solo el ALB es publico.
- RDS acepta 5432 exclusivamente desde el Security Group ECS.
- S3 bloquea acceso publico, exige TLS, cifra con SSE-S3 y mantiene versiones.
- El Task Role backend solo lista el bucket de la aplicacion y administra sus objetos.
- El Execution Role solo descarga ECR, escribe logs y obtiene los dos secretos requeridos.
- WAF aplica reglas administradas comunes, entradas maliciosas conocidas y rate limiting.

## 6. Limitaciones de costo y disponibilidad

Esta arquitectura no puede garantizar costo cero. NAT Gateway, ALB, Fargate, RDS, WAF, Route 53, IPv4, logs y transferencia pueden generar cargos aunque una cuenta disponga de creditos Free Tier. `desired_count=0` evita arrancar tareas durante el primer apply, pero RDS, ALB, NAT y WAF ya generan consumo.

Para reducir costo se utiliza una NAT Gateway compartida y RDS Single-AZ. En produccion con exigencia de alta disponibilidad se recomiendan una NAT por AZ, RDS Multi-AZ, al menos dos tareas backend/frontend y alarmas con SNS.

## 7. Validaciones ejecutadas

- Composer validate: correcto; Composer audit: 0 avisos.
- PHPUnit: 189 pruebas, 1393 aserciones, todas correctas.
- Frontend privado: 3 pruebas de validacion correctas y build Vite correcto.
- Frontend publico: build Vite correcto.
- NPM audit de ambos lockfiles: 0 avisos.
- Tres imágenes de produccion: build correcto; health y fallback SPA probados en contenedores.
- Lambda: 5 pruebas correctas en Python 3.13; SAM template valido.
- Terraform: `fmt -check` y `validate` correctos, sin warnings.
- No se ejecuto `terraform plan` porque requiere valores/cuenta AWS; no se ejecuto `apply`, push ECR ni otra mutacion AWS.
