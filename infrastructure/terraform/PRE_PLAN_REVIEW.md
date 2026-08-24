# Revisión final previa al primer plan

Fecha de revisión: 2026-08-19. Alcance: `infrastructure/template.yaml`,
`infrastructure/terraform` y las comprobaciones de aplicación estrictamente
necesarias para validar routing, migración y seeder. No se ejecutó `apply`, no
se usaron credenciales AWS y no se creó ni modificó ningún recurso AWS.

## A. Errores bloqueantes antes de `terraform plan`

1. Se deben sustituir `app_secret_arn` e `initial_admin_email` del tfvars. El
   primero debe ser el ARN real de un secreto JSON ya existente; no se debe
   guardar su contenido en Terraform.
2. El bloque `backend "s3" {}` requiere inicializar Terraform con la
   configuración de un bucket de state ya existente (y, si se adopta, el
   mecanismo de locking). Esos identificadores aún no están en el repositorio.
3. Se necesitan credenciales AWS de solo el alcance adecuado para que el
   provider lea identidad, zonas de disponibilidad y genere el plan. No se
   conoce cuenta, región definitiva ni perfil/rol.
4. En esta estación no está instalado el ejecutable `terraform`; por ello no
   fue posible cerrar la comprobación con `terraform fmt -check` y
   `terraform validate`. La revisión HCL fue estática, no sustituye esas dos
   comprobaciones.

El dominio no es bloqueante. Con dominios, zona y certificado vacíos, el plan
crea ALB HTTP y omite ACM y Route 53.

Durante la revisión se corrigió el cálculo de `APP_URL`, `FRONTEND_URL` y CORS:
ahora usa `https` sólo cuando realmente existe listener HTTPS; un dominio
proporcionado antes del certificado ya no anuncia HTTPS inexistente.

## B. Riesgos antes de `terraform apply`

- NAT Gateway, ALB, RDS, WAF, IPv4 pública del NAT y Secrets Manager pueden
  cobrar aunque los tres servicios ECS permanezcan en cero.
- `enable_nat_gateway=false` deja las tareas privadas sin salida para ECR,
  Secrets Manager, API Gateway y SMTP; sólo S3 tiene endpoint de VPC.
- El fallback `/__public/*` sin dominio no convierte al frontend público en una
  SPA funcional: sus rutas reales son `/`, `/consulta-ticket`, `/tickets/*` y
  `/rutas/*`, y sus assets usan rutas de raíz. Hace falta un hostname o una
  decisión de build/base-path y reglas adicionales.
- `db_deletion_protection=true` bloquea la destrucción de RDS. Si se cambia a
  `false`, Terraform pide snapshot final (`skip_final_snapshot=false`), pero el
  nombre fijo puede colisionar con un snapshot de una destrucción anterior.
- La misma variable de protección de RDS controla también la protección contra
  borrado del ALB. Es un acoplamiento operativo inesperado.
- S3 tiene `force_destroy=false` y versioning: un bucket con objetos/versiones
  impedirá destroy. ECR también usa `force_delete=false`.
- La regla S3 elimina versiones no actuales a los 30 días. Es recuperación
  limitada, no backup permanente.
- El Task Role puede borrar objetos S3. Es necesario para el comportamiento
  actual, pero aumenta el impacto de un fallo de aplicación.
- Los Security Groups de ECS permiten toda salida IPv4. La entrada sí queda
  limitada al ALB en 8080.
- API Gateway es público y no tiene authorizer. El handler exige
  `x-internal-token`, tiene throttling y no registra el payload, pero el secreto
  compartido es la única autenticación.
- Las alarmas CloudWatch no tienen acciones SNS: detectan, pero no notifican.
- Reejecutar `db:seed --force` vuelve a ejecutar `updateOrCreate` sobre el admin,
  cambia su contraseña y vuelve a marcar `must_change_password=true`.
- Los tags ECR son inmutables. `latest` no es una estrategia segura; usar un tag
  de release único antes de activar servicios.

## C. Decisiones y datos pendientes

- Cuenta/rol AWS y región definitiva.
- Bucket y key del state remoto; estrategia de locking compatible con la
  versión de Terraform elegida.
- ARN del secreto de aplicación ya creado y email del administrador inicial.
- Aceptación de clase, almacenamiento, Single-AZ y retención de RDS.
- Dominio privado, dominio público, Hosted Zone y certificado: todos siguen
  desconocidos y no deben inventarse.
- Hostname público es la opción compatible sin cambiar los frontends. La otra
  opción es reconstruir el frontend público con un base path y adaptar router,
  assets y reglas ALB; no está implementada.
- Email de presupuesto, proveedor SMTP y credenciales SMTP.
- Tags inmutables de las tres imágenes antes de subir `desired_count`.
- Confirmar si WAF y NAT se aceptan desde el primer apply por su costo.

## SAM y Terraform: propiedad única

Terraform es la única fuente de infraestructura de producción.

| Capacidad | SAM | Terraform |
|---|---|---|
| Lambda de validación | Definición local, deshabilitada por defecto | `aws_lambda_function.ticket_validation` |
| HTTP API y ruta | Evento `HttpApi` sólo local | API, integración, ruta y stage v2 |
| IAM Lambda | Política implícita sólo local | rol y política explícitos |
| CloudWatch Logs | Implícitos si se desplegara local template | log group explícito con retención |
| Permiso Lambda | Implícito por evento sólo local | `aws_lambda_permission.api_gateway` |

Antes de esta revisión ambos describían la misma capacidad desplegable. El
template SAM ahora condiciona función, evento, IAM implícito, API, permiso y
output a `EnableLocalResources=true`, cuyo default es `false`. El parámetro sólo
se habilita al usar `sam local`; queda prohibido ejecutar `sam deploy` para
producción. Terraform empaqueta directamente el mismo código con `archive_file`.
No hay otros recursos compartidos en SAM. Ningún stack SAM debe coexistir con
esta infraestructura de producción.

Ejemplo local (no despliega recursos):

```powershell
sam build --template-file infrastructure/template.yaml `
  --parameter-overrides ParameterKey=EnableLocalResources,ParameterValue=true
sam local start-api --template-file .aws-sam/build/template.yaml `
  --parameter-overrides ParameterKey=EnableLocalResources,ParameterValue=true
```

## D. Inventario de recursos

| Recurso | Archivo | Propósito | Costo fijo/por existir | Dependencias principales |
|---|---|---|---|---|
| VPC | `networking.tf` | Red `10.30.0.0/16` | Bajo/despreciable | ninguna |
| 2 subnets públicas | `networking.tf` | ALB y NAT | Bajo/despreciable | VPC, AZ |
| 2 subnets app privadas | `networking.tf` | tareas Fargate sin IP pública | Bajo/despreciable | VPC, NAT opcional |
| 2 subnets data privadas | `networking.tf` | RDS | Bajo/despreciable | VPC |
| Internet Gateway | `networking.tf` | salida/entrada pública | Bajo/despreciable | VPC |
| EIP + NAT Gateway | `networking.tf` | salida de tareas privadas | Continuo | subnet pública, IGW |
| route tables/asociaciones | `networking.tf` | routing público/app/data | Bajo/despreciable | VPC, IGW/NAT |
| endpoint gateway S3 | `networking.tf` | S3 sin pasar por NAT | Bajo/despreciable | VPC, route tables app |
| 3 repositorios ECR | `ecr.tf` | imágenes backend/frontends | Por almacenamiento/consumo | cuenta AWS |
| ECS Cluster | `ecs.tf` | ejecución Fargate e Insights | Bajo solo; telemetría por consumo | IAM/logs |
| 4 Task Definitions | `ecs.tf` | backend, 2 frontends, migración | Bajo/despreciable | ECR, IAM, RDS, S3, secrets |
| 3 ECS Services | `ecs.tf` | workloads permanentes | Por consumo; cero con desired 0 | cluster, TG, tasks |
| ALB | `alb.tf` | entrada HTTP/HTTPS | Continuo | subnets públicas, SG |
| 3 Target Groups | `alb.tf` | backend y dos frontends | Ligado a ALB | VPC |
| listeners/reglas | `alb.tf` | TLS/HTTP y routing | Ligado a ALB | ALB, TG, ACM opcional |
| ACM certificate/validation | `alb.tf` | TLS opcional | Bajo/despreciable | dominios + Hosted Zone |
| Route 53 aliases | `alb.tf` | DNS opcional | Bajo/por consumo; zona es externa | Hosted Zone + dominios |
| RDS PostgreSQL | `storage.tf` | base de datos | Continuo | data subnets, SG, secret |
| DB subnet group | `storage.tf` | ubicación privada RDS | Bajo/despreciable | data subnets |
| S3 bucket/configuración | `storage.tf` | archivos Laravel | Por almacenamiento/solicitud | IAM, endpoint |
| secreto maestro RDS | `storage.tf` (indirecto) | password administrado | Continuo/bajo | RDS |
| secreto app externo | variable | APP/Lambda/admin/mail | Continuo/bajo, no lo crea este módulo | aprovisionamiento previo |
| IAM roles/policies | `iam.tf`, `lambda.tf` | ejecución y permisos mínimos | Bajo/despreciable | ECR, S3, secrets, logs |
| log groups/alarmas | `ecs.tf`, `lambda.tf`, `observability.tf` | logs y métricas | Por ingesta/retención/alarmas | ECS, Lambda, ALB, RDS |
| Lambda | `lambda.tf` | validación pública | Por consumo | zip, IAM, secret |
| API Gateway HTTP | `lambda.tf` | `POST /tickets/verify` | Por consumo | Lambda + permiso |
| WAF Web ACL | `waf.tf` | reglas managed y rate limit | Continuo + consumo | ALB |
| AWS Budget | `observability.tf` | alertas 80/100 % | Bajo/despreciable según uso | email opcional |
| 3 Security Groups | `security-groups.tf` | ALB, ECS y RDS | Bajo/despreciable | VPC |

No se crea Hosted Zone, secreto de aplicación, backend S3 del state, locking,
SNS, SES, VPC endpoints salvo S3, S3 trigger, Lambda de procesamiento digital,
DLQ ni scheduler.

## Routing real del ALB

El listener activo es HTTPS si hay ARN de certificado o si Terraform puede
crear/validar uno; de lo contrario es HTTP. Cuando hay HTTPS, HTTP sólo redirige.
Las prioridades menores se evalúan primero.

| Path/Host | Target Group | ECS Service | Puerto |
|---|---|---|---:|
| cualquier host, `/api/*` o `/up` (prioridad 10) | backend | backend | 8080 |
| host igual a `public_domain` (prioridad 20) | public_frontend | public-frontend | 8080 |
| sin `public_domain`, sólo `/__public/*` (prioridad 20) | public_frontend | public-frontend | 8080 |
| resto/default | frontend | frontend | 8080 |

El routing por hostname sí separa correctamente ambas SPA. El fallback técnico
por path no corresponde con las rutas reales del frontend público y queda como
decisión pendiente; no se inventaron reglas adicionales.

## ECS

| Task/Service | Imagen y ECR | CPU / memoria | Puerto | desired inicial | Health check | Roles | SG/IP pública |
|---|---|---:|---:|---:|---|---|---|
| backend | `terminal302-backend:<backend_image_tag>` | 512 / 1024 MiB | 8080 | 0 | contenedor y TG `GET /up` | task `backend_task`; execution `ecs_execution` | `ecs`, no |
| frontend | `terminal302-frontend:<frontend_image_tag>` | 256 / 512 MiB | 8080 | 0 | contenedor y TG `GET /healthz` | task `frontend_task`; execution `ecs_execution` | `ecs`, no |
| public-frontend | `terminal302-public-frontend:<public_frontend_image_tag>` | 256 / 512 MiB | 8080 | 0 | contenedor y TG `GET /healthz` | task `frontend_task`; execution `ecs_execution` | `ecs`, no |
| migration (Run Task, sin Service) | imagen backend | 512 / 1024 MiB | ninguno | no aplica | ninguno | task `backend_task`; execution `ecs_execution` | se debe lanzar con `ecs`, sin IP pública |

Backend y migration reciben: `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`,
`FRONTEND_URL`, `CORS_ALLOWED_ORIGINS`, locales, configuración de logs, conexión
PostgreSQL, identidad del admin, sesión/cache/queue, S3, mail y URL/driver/timeout
de Lambda. Secrets: `APP_KEY`, `LAMBDA_INTERNAL_TOKEN`, `MAIL_USERNAME`,
`MAIL_PASSWORD` y `DB_PASSWORD`; migration añade `INITIAL_ADMIN_PASSWORD`.
Los dos frontends no reciben variables ni secrets en runtime porque Vite queda
compilado en la imagen. Todos los Services declaran `assign_public_ip=false`.

Que las imágenes no existan no bloquea el primer apply: AWS acepta las Task
Definitions y, con `desired_count=0`, ECS no inicia tareas ni intenta hacer pull.
Después se publican tags inmutables, se actualizan variables y se aplica el
cambio de tasks/desired count.

## RDS

- PostgreSQL 16, `gp3`, 20 GiB por defecto, cifrado habilitado.
- DB subnet group usa sólo las dos subnets `data` privadas.
- `publicly_accessible=false`, puerto 5432, Single-AZ (`multi_az=false`).
- SG permite 5432 únicamente desde el SG común de ECS.
- Backups 7 días, auto minor upgrades, Performance Insights deshabilitado.
- Protección contra borrado habilitada por defecto.
- `skip_final_snapshot=false`, snapshot final fijo y tags copiados.

No se cambió ninguna protección. El default prioriza evitar pérdida: destroy
falla mientras deletion protection siga activa; tras desactivarla exige snapshot
final. S3/ECR también pueden impedir una destrucción incompleta con datos.

## S3

Bucket Laravel: `${project}-${environment}-files-${account_id}`. Tiene versioning,
bloqueo público completo, SSE-S3 AES256, TLS obligatorio y lifecycle para abortar
multipart incompleto a 7 días y borrar versiones no actuales a 30 días. No hay
configuración CORS.

El backend Task Role permite `ListBucket` y `GetObject`, `PutObject`,
`DeleteObject` sobre el bucket. Laravel puede guardar y obtener objetos. También
puede generar URLs prefirmadas usando credenciales temporales del Task Role, sin
access keys estáticas. Si el navegador hará fetch/upload directo cross-origin,
se deberá definir CORS explícito; no hace falta para operaciones servidor-S3.

## Secrets Manager

| Secret | Quién lo crea | Consumidor | Variable resultante |
|---|---|---|---|
| JSON `app_secret_arn` | externo, antes del despliegue | backend/migration | `APP_KEY` |
| mismo JSON | externo | backend/migration y Lambda | `LAMBDA_INTERNAL_TOKEN` / clave leída por Lambda |
| mismo JSON | externo | migration | `INITIAL_ADMIN_PASSWORD` |
| mismo JSON | externo | backend/migration | `MAIL_USERNAME`, `MAIL_PASSWORD` |
| master secret RDS | RDS administrado | backend/migration | `DB_PASSWORD` |

`DB_USERNAME` no es un tfvar sensible: se pasa como variable normal y también
forma parte de las credenciales administradas por RDS. No se muestran valores.
El execution role, no el Task Role, lee los secrets para inyectarlos en ECS.

## Lambda pública

- Runtime Python 3.13 x86_64; handler `handler.lambda_handler`; 128 MiB; timeout 5 s.
- Trigger: API Gateway HTTP API, `POST /tickets/verify`, stage `$default`.
- Variables: ARN del secreto y clave JSON `LAMBDA_INTERNAL_TOKEN`.
- IAM: asumir rol Lambda, leer sólo `app_secret_arn`, crear streams/escribir en su
  log group precreado.
- Logs: `/aws/lambda/<project>-<environment>-public-ticket-validation`, 14 días
  por defecto.
- API: proxy payload 2.0, auto deploy, burst 50/rate 25 y permiso InvokeFunction
  restringido al execution ARN de esa API.

No existe `aws_s3_bucket_notification`, permiso S3→Lambda ni event source S3.
No se debe añadir: el handler actual valida un payload HTTP y no consume eventos
de tickets digitales.

## Dominio, fases y migración

El primer plan/apply de base puede realizarse sin dominio. ACM, validación DNS y
aliases Route 53 son condicionales. WAF es independiente del dominio y puede
habilitarse/deshabilitarse con `enable_waf`.

Toda la infraestructura base puede expresarse en un apply con desired counts en
cero. Operativamente el despliegue tiene fases: infraestructura/ECR/RDS; build y
push; nuevo plan/apply de Task Definitions y services; Run Task de migración;
activación de servicios. No se ejecutan migraciones en el entrypoint.

La Task Definition `migration` ejecuta por defecto `php artisan migrate --force`.
Se lanza puntualmente con `aws ecs run-task`, usando los outputs de cluster, task,
subnets app y SG ECS, con `assignPublicIp=DISABLED`; en la inicialización se
sobrescribe el comando por `php artisan migrate --force && php artisan db:seed
--force`. Se debe esperar estado STOPPED, exigir exit code 0 y revisar logs antes
de activar servicios.

El seeder obtiene `INITIAL_ADMIN_PASSWORD` sólo del secreto inyectado a la task,
la hashea y nunca la imprime en producción. Crea/actualiza el admin con
`must_change_password=true`. El frontend redirige a `/cambiar-contrasena` y el
backend aplica `password.changed` a las áreas funcionales, dejando accesible el
endpoint autenticado de cambio. La obligación está implementada.

## E. Comandos seguros hasta plan

Estos comandos se listan para ejecución manual; ninguno fue ejecutado aquí:

```powershell
Set-Location infrastructure/terraform
Copy-Item terraform.tfvars.example terraform.tfvars
# Editar terraform.tfvars y reemplazar únicamente los placeholders requeridos.
terraform fmt -check -recursive
terraform init -backend-config="bucket=<TF_STATE_BUCKET>" `
  -backend-config="key=terminal302/production.tfstate" `
  -backend-config="region=<AWS_REGION>" `
  -backend-config="encrypt=true"
terraform validate
terraform plan -out=plan.out
terraform show -no-color plan.out
```

Si se usa locking, agregar sólo la configuración correspondiente a la estrategia
ya decidida. `init` y `plan` requieren acceso al backend/AWS, por lo que no deben
ejecutarse hasta configurar credenciales y revisar los valores. No ejecutar
`apply`, `destroy`, pushes ECR, migraciones AWS ni creación manual de recursos
como parte de esta revisión.
