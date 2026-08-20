# Despliegue de Terminal302 en AWS

Esta guia prepara un despliegue manual y reproducible. No se debe ejecutar `terraform apply` hasta revisar el plan y el costo estimado.

## 1. Arquitectura

```text
Usuarios
   |
Route 53 + ACM
   |
AWS WAF
   |
Application Load Balancer (subredes publicas)
   |-- host privado, /* ------> ECS frontend privado (subredes app privadas)
   |-- host publico, /* ------> ECS frontend publico  (subredes app privadas)
   `-- /api/* y /up ----------> ECS Laravel/Nginx     (subredes app privadas)
                                      |-- RDS PostgreSQL (subredes de datos privadas)
                                      |-- S3 privado (tickets, QR, plantillas y eventos)
                                      `-- API Gateway HTTP -> Lambda validacion publica

ECR -> imagenes ECS
Secrets Manager -> variables secretas ECS/Lambda
ECS/Lambda/ALB/RDS -> CloudWatch
```

S3 Trigger para entregas digitales: **PENDIENTE DE IMPLEMENTAR**. No se conecta el bucket a la Lambda de validacion porque sus contratos no coinciden.

## 2. Advertencia de costos

ECS Fargate + ALB + RDS + NAT Gateway + WAF no constituye una arquitectura garantizada por el nivel gratuito. Las cuentas nuevas desde el 15 de julio de 2025 usan creditos con plazo; cuentas anteriores conservan el esquema legacy aplicable. Antes de crear recursos:

1. Consulta Billing > Free Tier y la fecha/tipo de tu cuenta.
2. Configura `budget_alert_email` y un limite mensual.
3. Revisa precios en AWS Pricing Calculator para la region seleccionada.
4. Mantiene `desired_count=0` durante el primer apply.
5. Si no puedes aceptar cargos, no ejecutes `terraform apply` con esta arquitectura.

`enable_nat_gateway=false` reduce un costo importante, pero las tareas privadas no podran descargar ECR, leer Secrets Manager ni alcanzar API Gateway/SMTP sin VPC endpoints o una alternativa de salida. No se recomienda desactivarlo en el primer despliegue.

## 3. Prerrequisitos

- Cuenta AWS con MFA en el usuario root y un rol administrativo temporal para bootstrap.
- AWS CLI v2, Docker, Git y Terraform >= 1.6.
- Dominio y Hosted Zone de Route 53, o un certificado ACM existente. Sin dominio se puede probar HTTP usando el DNS del ALB; el frontend publico queda solo bajo la ruta tecnica `/__public/*` y no es funcional como SPA completa.
- Permisos para VPC, ECS, ECR, RDS, IAM, S3, Lambda, API Gateway, CloudWatch, WAF, ACM, Route 53, Secrets Manager y Budgets.

Configura la CLI sin guardar claves en el repositorio:

```bash
aws configure sso
aws sts get-caller-identity
export AWS_REGION=us-east-1
export AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
```

En PowerShell usa `$env:AWS_REGION` y `$env:AWS_ACCOUNT_ID`.

## 4. Backend remoto de Terraform

El state contiene metadatos sensibles. Crea una vez un bucket exclusivo, con versionado/cifrado/bloqueo publico, y una tabla DynamoDB para locking. Usa nombres propios y no los publiques en Git.

```bash
aws s3api create-bucket --bucket <TF_STATE_BUCKET> --region "$AWS_REGION"
aws s3api put-bucket-versioning --bucket <TF_STATE_BUCKET> --versioning-configuration Status=Enabled
aws s3api put-public-access-block --bucket <TF_STATE_BUCKET> --public-access-block-configuration BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true
aws dynamodb create-table --table-name <TF_LOCK_TABLE> --billing-mode PAY_PER_REQUEST --attribute-definitions AttributeName=LockID,AttributeType=S --key-schema AttributeName=LockID,KeyType=HASH
```

Para una region distinta de `us-east-1`, agrega a `create-bucket` `--create-bucket-configuration LocationConstraint="$AWS_REGION"`.

Inicializa:

```bash
cd infrastructure/terraform
terraform init \
  -backend-config="bucket=<TF_STATE_BUCKET>" \
  -backend-config="key=terminal302/production.tfstate" \
  -backend-config="region=$AWS_REGION" \
  -backend-config="dynamodb_table=<TF_LOCK_TABLE>" \
  -backend-config="encrypt=true"
```

## 5. Secrets Manager

Genera valores fuera del repositorio:

```bash
APP_KEY="base64:$(openssl rand -base64 32)"
LAMBDA_TOKEN="$(openssl rand -hex 32)"
ADMIN_PASSWORD="$(openssl rand -base64 24)"
```

Crea un JSON local temporal fuera del repositorio con todas las claves, incluso si SMTP aun usa `log`:

```json
{
  "APP_KEY": "base64:...",
  "LAMBDA_INTERNAL_TOKEN": "...",
  "INITIAL_ADMIN_PASSWORD": "...",
  "MAIL_USERNAME": "",
  "MAIL_PASSWORD": ""
}
```

```bash
aws secretsmanager create-secret \
  --name terminal302/production/app \
  --secret-string file://<RUTA_JSON_TEMPORAL> \
  --region "$AWS_REGION"
aws secretsmanager describe-secret \
  --secret-id terminal302/production/app \
  --query ARN --output text
```

Elimina de forma segura el archivo temporal después de comprobar el secreto. RDS genera y administra su propio secreto de contraseña mediante `manage_master_user_password`; Terraform pasa ese secreto a ECS sin incluir el valor en variables Terraform.

## 6. Variables Terraform

```bash
cp terraform.tfvars.example terraform.tfvars
```

Completa `app_secret_arn`, `initial_admin_email`, dominios, Hosted Zone, correo y presupuesto. `terraform.tfvars` está ignorado. No escribas valores secretos dentro de él.

Variables principales:

| Variable | Servicio | Origen | Secret | Uso |
|---|---|---|---|---|
| APP_KEY | backend | Secrets Manager | Si | Cifrado/firma Laravel |
| DB_PASSWORD | backend | Secreto administrado RDS | Si | Conexion PostgreSQL |
| DB_USERNAME | backend/RDS | Terraform | No | Usuario maestro inicial |
| DB_HOST | backend | Output RDS | No | Endpoint privado |
| INITIAL_ADMIN_PASSWORD | migration task | Secrets Manager | Si | Solo seeding inicial |
| LAMBDA_INTERNAL_TOKEN | backend/Lambda | Secrets Manager | Si | Autenticacion interna |
| MAIL_USERNAME/PASSWORD | backend | Secrets Manager | Si | SMTP cuando se habilite |
| AWS_BUCKET | backend | Terraform | No | Bucket privado |
| AWS_DEFAULT_REGION | backend | Terraform | No | Region SDK |
| VITE_API_URL | builds Vue | argumento Docker | No | `/api` en produccion |
| APP_URL/FRONTEND_URL | backend | dominios Terraform | No | URLs HTTPS |
| CORS_ALLOWED_ORIGINS | backend | dominios Terraform | No | Origenes explicitos |

No se definen `AWS_ACCESS_KEY_ID` ni `AWS_SECRET_ACCESS_KEY`: el SDK usa credenciales temporales del ECS Task Role.

## 7. Revisar y crear infraestructura base

Formatea, valida y genera un plan:

```bash
terraform fmt -check -recursive
terraform validate
terraform plan -out=plan.out
terraform show plan.out
```

Verifica especialmente que RDS sea `publicly_accessible=false`, las tareas tengan `assign_public_ip=false`, S3 sea privado y no aparezcan reemplazos/destrucciones inesperados. Después de aprobación humana:

```bash
terraform apply plan.out
```

El primer apply deja los tres servicios ECS en cero tareas. Crea VPC, subredes, NAT, Security Groups, ECR, ECS, ALB, RDS, S3, IAM, logs, Lambda/API Gateway, WAF, ACM/Route 53 opcionales, alarmas y presupuesto.

## 8. Construir y publicar ECR

Usa una etiqueta inmutable, por ejemplo el SHA de Git:

```bash
RELEASE_TAG=$(git rev-parse --short=12 HEAD)
ECR_REGISTRY="$AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com"
aws ecr get-login-password --region "$AWS_REGION" | docker login --username AWS --password-stdin "$ECR_REGISTRY"

docker build -f backend/Dockerfile.production -t terminal302-backend:$RELEASE_TAG backend
docker build -f frontend/Dockerfile.production --build-arg VITE_API_URL=/api -t terminal302-frontend:$RELEASE_TAG frontend
docker build -f public-frontend/Dockerfile.production --build-arg VITE_API_URL=/api -t terminal302-public-frontend:$RELEASE_TAG public-frontend

docker tag terminal302-backend:$RELEASE_TAG "$ECR_REGISTRY/terminal302-backend:$RELEASE_TAG"
docker tag terminal302-frontend:$RELEASE_TAG "$ECR_REGISTRY/terminal302-frontend:$RELEASE_TAG"
docker tag terminal302-public-frontend:$RELEASE_TAG "$ECR_REGISTRY/terminal302-public-frontend:$RELEASE_TAG"

docker push "$ECR_REGISTRY/terminal302-backend:$RELEASE_TAG"
docker push "$ECR_REGISTRY/terminal302-frontend:$RELEASE_TAG"
docker push "$ECR_REGISTRY/terminal302-public-frontend:$RELEASE_TAG"
```

Actualiza los tres `*_image_tag` en `terraform.tfvars`, vuelve a ejecutar plan/apply. Las variables `VITE_*` existen solo durante el build; no se pueden cambiar en una tarea ya construida.

## 9. Migracion inicial a RDS

La task `terminal302-production-migration` ejecuta por defecto solo `php artisan migrate --force`. En el primer despliegue también necesita los catálogos, roles y administrador:

```bash
CLUSTER=$(terraform output -raw ecs_cluster_name)
TASK=$(terraform output -raw migration_task_definition)
SUBNETS=$(terraform output -json app_subnet_ids)
SG=$(terraform output -raw ecs_security_group_id)

aws ecs run-task \
  --cluster "$CLUSTER" \
  --launch-type FARGATE \
  --task-definition "$TASK" \
  --network-configuration "awsvpcConfiguration={subnets=$SUBNETS,securityGroups=[$SG],assignPublicIp=DISABLED}" \
  --overrides '{"containerOverrides":[{"name":"migration","command":["/bin/sh","-c","php artisan migrate --force && php artisan db:seed --force"]}]}'
```

Espera `STOPPED`, comprueba `exitCode=0` y revisa `/terminal302/production/backend`. Ejecuta el seeder completo solo en la inicialización: vuelve a aplicar `updateOrCreate` al administrador. En despliegues posteriores usa la tarea sin override para aplicar únicamente migraciones.

## 10. Activar servicios ECS

Cambia a 1 (o 2 para alta disponibilidad) los tres `desired_count`, ejecuta plan y apply. Verifica:

```bash
aws ecs list-services --cluster "$CLUSTER"
aws ecs list-tasks --cluster "$CLUSTER" --service-name backend
curl -i "$(terraform output -json application_urls | jq -r .private)/up"
```

Prueba login, creación/render de ticket, descarga/presigned URL, consulta pública y fallback Lambda.

## 11. ALB, HTTPS, Route 53 y WAF

- Con `app_domain`, `public_domain` y `hosted_zone_id`, Terraform solicita/valida ACM y crea aliases Route 53.
- Si proporcionas `certificate_arn`, debe estar en la misma región que el ALB.
- HTTP redirige a HTTPS cuando existe certificado.
- `/api/*` y `/up` siempre llegan al backend; el hostname publico llega al SPA público; el resto al SPA privado.
- WAF queda asociado al ALB cuando `enable_waf=true`.

No guardes certificados dentro de imágenes. El ALB termina TLS y Laravel confía en `X-Forwarded-*` bajo el supuesto de que sus tareas solo son alcanzables desde el ALB.

## 12. S3 y Lambda

S3 usa IAM Task Role. Valida escritura creando una plantilla/ticket, no con credenciales estáticas. La Lambda existente se empaqueta desde `lambda/public-ticket-validation/src`, lee el token de Secrets Manager en cold start, registra JSON sin datos del ticket y se publica tras API Gateway con throttling.

No agregues un S3 notification al handler actual. La Lambda de entrega digital y su contrato/idempotencia/DLQ están **PENDIENTES DE IMPLEMENTAR**.

## 13. Correo, queues y scheduler

- Con `mail_mailer=log`, recuperación de contraseña y tickets digitales no se entregan. Configura SMTP/SES antes de considerarlos operativos.
- No hay Jobs/`ShouldQueue`; `QUEUE_CONNECTION=sync` evita requerir un worker inexistente.
- No hay Scheduler registrado; no se crea EventBridge Schedule.
- Mientras no exista la Lambda S3, `tickets:process-digital-deliveries` puede ejecutarse como tarea ECS puntual sobrescribiendo el comando del task backend. No lo programes sin evaluar idempotencia y concurrencia.

## 14. CloudWatch y operación

Logs:

- `/terminal302/production/backend`
- `/terminal302/production/frontend`
- `/terminal302/production/public-frontend`
- `/aws/lambda/terminal302-production-public-ticket-validation`

Se crean alarmas de 5xx del ALB, targets backend no saludables y poco espacio RDS. No envían notificaciones hasta agregar SNS/acciones. El presupuesto sí envía email si `budget_alert_email` está definido.

## 15. Rollback

Las etiquetas ECR son inmutables y se conservan 10 imágenes. Para rollback:

1. Cambia los tres tags en `terraform.tfvars` a una release conocida.
2. Ejecuta `terraform plan` y confirma que solo cambian task definitions/services.
3. Aplica. ECS circuit breaker revierte un despliegue que no alcanza estado estable.
4. No reviertas migraciones automáticamente. Restaura un snapshot RDS en una instancia nueva y valida compatibilidad si una migración fuera irreversible.
5. S3 mantiene versiones por 30 días para recuperar objetos reemplazados/eliminados.

## 16. Checklist exacto

1. Activar MFA, revisar tipo/creditos Free Tier y aceptar el costo estimado.
2. Elegir región y configurar AWS CLI/SSO.
3. Crear presupuesto/alerta (Terraform lo hace si se informa email).
4. Preparar dominio, Hosted Zone y/o certificado ACM.
5. Crear backend remoto de Terraform.
6. Crear y poblar el secreto JSON de aplicación.
7. Copiar y completar `terraform.tfvars`, con desired counts en 0.
8. Ejecutar `fmt`, `validate`, `plan` y revisión humana.
9. Aplicar infraestructura base.
10. Construir las tres imágenes de producción.
11. Escanearlas y publicar tags inmutables en ECR.
12. Actualizar tags Terraform y aplicar task definitions.
13. Ejecutar una vez migraciones + seeders y comprobar exit code/logs.
14. Configurar SMTP/SES y probar recuperación de contraseña.
15. Cambiar desired counts a 1 y aplicar.
16. Esperar servicios ECS estables y target groups healthy.
17. Validar HTTPS, Route 53, reglas ALB y WAF.
18. Probar login Bearer, roles, tickets, QR, S3 y consulta pública.
19. Probar Lambda y verificar fallback controlado.
20. Revisar CloudWatch Logs, alarmas y Billing.
21. Documentar release, tags, fecha, operador y procedimiento de rollback.

## 17. Pendientes fuera del repositorio

- Credenciales/SSO y permisos AWS.
- Decisión de región, dominios, Hosted Zone, correo operativo y presupuesto aceptado.
- Creación/aprobación final de infraestructura (`terraform apply`).
- Publicación ECR y ejecución de migraciones.
- Suscripción SNS para alarmas.
- Implementación funcional de Lambda de entrega digital/S3 Trigger y proveedor WhatsApp.
- Prueba de carga, pentest, estrategia de recuperación y decisión Multi-AZ antes de tráfico crítico.
