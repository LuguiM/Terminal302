# Guía de entorno local con simulación de AWS Lambda

Esta guía propone cómo completar el entorno de desarrollo de Terminal302 para crear y probar funciones AWS Lambda sin reemplazar la API Laravel ni los frontends Vue existentes. La opción recomendada es **AWS SAM CLI sobre Docker**: ejecuta el runtime de Lambda, aproxima API Gateway y ofrece una ruta directa hacia un despliegue posterior con CloudFormation.

## 1. Alcance y arquitectura propuesta

Laravel continúa como API principal, responsable de Sanctum, reglas de negocio y PostgreSQL. Lambda se reserva para trabajos aislados o que escalen de forma independiente: validación pública de tickets, procesamiento de archivos, notificaciones o consumidores de eventos.

```text
Navegador
  |-- localhost:5173 ------> Vue privado
  |-- localhost:5174 ------> Vue público
  |                              |
  `------------------------------v
                         Laravel API :8302
                            |          |
                            |          `--> PostgreSQL (postgres:5432)
                            |
                            `--> Lambda local / API Gateway :3001
```

El flujo recomendado es `Vue -> Laravel -> Lambda`. El frontend conserva una sola URL base (`VITE_API_URL`), Sanctum sigue centralizado y no hay que exponer credenciales ni resolver CORS por función. `Vue -> API Gateway/Lambda` solo conviene para endpoints deliberadamente públicos con autorización, límites y CORS definidos en infraestructura.

Laravel carga el ticket y envía a Lambda únicamente `codigo_ticket`, `estado`, `fecha_operacion` y la fecha operativa actual. La función no se conecta a la red Compose, Laravel ni PostgreSQL. Si SAM no está disponible, Laravel aplica el mismo cálculo local.

## 2. Comparación de opciones

| Opción | Ventajas | Límites | Uso recomendado |
|---|---|---|---|
| **AWS SAM CLI** | IaC desplegable, runtime Lambda en Docker, `local invoke`, API Gateway local y depuración | No emula todos los servicios AWS; una llamada SDK puede llegar a AWS real | Opción base para Terminal302 |
| **Lambda Runtime Interface Emulator (RIE)** | Ligero y cercano al protocolo/runtime de una imagen Lambda | No simula API Gateway ni otros servicios; exige gestionar imagen y eventos | Funciones empaquetadas como imagen |
| **LocalStack** | Emula Lambda junto con S3, SQS, SNS, DynamoDB y otros servicios | Más consumo/configuración; paridad incompleta y capacidades dependientes de edición | Integración con varios servicios AWS |
| **Mocks** | Rápidos, deterministas y sin Docker | No validan runtime, permisos, red ni IaC | Pruebas unitarias complementarias |

AWS confirma que SAM usa contenedores con el entorno de runtime de Lambda, pero advierte que las llamadas a otros servicios AWS apuntan a recursos reales si no se sustituyen o emulan. LocalStack reduce ese riesgo; ningún emulador reemplaza una prueba final en una cuenta AWS aislada.

## 3. Herramientas y puertos

Requisitos:

- Docker Desktop con contenedores Linux y Docker Compose.
- AWS SAM CLI instalado en el host (`sam --version`).
- AWS CLI v2, opcional para despliegue y pruebas contra AWS.
- `curl`, Postman o equivalente.

| Servicio | Host | Dentro de Docker |
|---|---:|---:|
| Laravel | `localhost:8302` | `backend:8000` |
| Vue privado | `localhost:5173` | `frontend:5173` |
| Vue público | `localhost:5174` | `public-frontend:5173` |
| SAM local | `localhost:3001` | asignado por SAM |
| PostgreSQL | `localhost:15432` | `postgres:5432` |

SAM descarga imágenes de runtime la primera vez, por lo que esa ejecución requiere Internet. No hacen falta credenciales AWS para una invocación puramente local. No reutilizar credenciales de producción: si una librería exige valores, usar credenciales ficticias y endpoints locales, o un perfil de desarrollo con permisos mínimos.

## 4. Estructura objetivo

Las carpetas `lambda/` e `infrastructure/` ya existen como marcadores. La primera función puede completar esta estructura:

```text
lambda/
`-- public-ticket-validation/
    |-- src/
    |   `-- handler.<ext>
    |-- events/
    |   |-- issued-current-date.json
    |   `-- invalid-payload.json
    `-- tests/
infrastructure/
|-- template.yaml
|-- env.local.example.json
`-- samconfig.toml
```

`env.local.json` debe ignorarse en Git si contiene secretos. `env.local.example.json` solo debe documentar nombres y valores seguros.

## 5. Implementación SAM incluida

`infrastructure/template.yaml` define `PublicTicketValidationFunction` con Python 3.13. La función recibe por `POST /tickets/verify` únicamente código, estado y fecha de operación; no accede a PostgreSQL ni recibe datos personales.

```yaml
AWSTemplateFormatVersion: '2010-09-09'
Transform: AWS::Serverless-2016-10-31
Description: Funciones locales de Terminal302

Resources:
  PublicTicketValidationFunction:
    Type: AWS::Serverless::Function
    Properties:
      CodeUri: ../lambda/public-ticket-validation/src/
      Handler: handler.lambda_handler
      Runtime: python3.13
      Events:
        VerifyTicket:
          Type: HttpApi
          Properties:
            Path: /tickets/verify
            Method: POST
```

Antes de adoptar un runtime, confirmar que siga soportado por Lambda. La arquitectura debe coincidir con la máquina y dependencias nativas; `x86_64` suele causar menos fricción en Windows y `arm64` puede convenir en hosts ARM.

Ejemplo de `infrastructure/env.local.json`:

```json
{
  "PublicTicketValidationFunction": {
    "INTERNAL_API_TOKEN": "local-only-change-me"
  }
}
```

En producción, usar Secrets Manager o SSM Parameter Store y permisos IAM; no copiar este archivo al artefacto.

## 6. Puesta en marcha

Desde la raíz:

```powershell
Copy-Item .env.example .env
Copy-Item backend\.env.example backend\.env
Copy-Item infrastructure\env.local.example.json infrastructure\env.local.json
docker compose up -d --build
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --seed
docker compose ps
```

Construir e iniciar la API local:

```powershell
sam validate --template-file infrastructure/template.yaml --lint
sam build --template-file infrastructure/template.yaml

sam local start-api `
  --template-file .aws-sam/build/template.yaml `
  --port 3001 `
  --env-vars infrastructure/env.local.json `
  --warm-containers lazy
```

En Bash, sustituir los acentos graves por `\` o escribir el comando en una línea. Probar:

```powershell
curl.exe -X POST http://localhost:3001/tickets/verify `
  -H "Content-Type: application/json" `
  -H "X-Internal-Token: local-only-change-me" `
  -d '{"codigo_ticket":"TKT-LOCAL-001","estado":"Emitido","fecha_operacion":"2026-07-16","current_date":"2026-07-16"}'
```

Invocar el handler con un evento, sin levantar HTTP:

```powershell
sam local invoke PublicTicketValidationFunction `
  --template-file .aws-sam/build/template.yaml `
  --event lambda/public-ticket-validation/events/issued-current-date.json `
  --env-vars infrastructure/env.local.json
```

Después de cambiar dependencias, reconstruir. Aunque SAM puede reflejar cambios directos de lenguajes interpretados en ciertos flujos, `sam build` explícito evita probar artefactos desactualizados.

Para que Laravel use SAM, cambiar `TICKET_VERIFICATION_DRIVER=http` en `backend/.env` y recrear el backend:

```powershell
docker compose up -d --force-recreate backend
```

Con `TICKET_VERIFICATION_DRIVER=local` la consulta sigue funcionando sin SAM y reporta `source: fallback`.

## 7. Integración con Laravel

### Laravel invoca Lambda local

La implementación usa estas variables:

```dotenv
TICKET_VERIFICATION_DRIVER=http
LAMBDA_BASE_URL=http://host.docker.internal:3001
LAMBDA_INTERNAL_TOKEN=local-only-change-me
LAMBDA_TIMEOUT_SECONDS=3
```

Laravel corre en Docker y SAM publica el puerto en el host; por eso usa `host.docker.internal`, no `localhost`. En Docker Desktop funciona directamente. En Linux puede ser necesario agregar al servicio `backend`:

```yaml
extra_hosts:
  - "host.docker.internal:host-gateway"
```

`PublicTicketVerificationService` centraliza la llamada, aplica timeout y usa el cálculo local si SAM falla o responde con un contrato inválido. Después de cambiar `backend/.env`, recrear el contenedor con `docker compose up -d --force-recreate backend`.

No enviar el token Sanctum del usuario salvo que la función deba actuar explícitamente con esa identidad. Para comunicación interna, usar otro contrato y credencial; en AWS, preferir IAM y endpoints privados.

### Trabajos asíncronos

El proyecto usa `QUEUE_CONNECTION=database`. Inicialmente, Laravel puede guardar el trabajo y un worker local invocar Lambda. Al adoptar SQS, LocalStack resulta útil para pruebas integradas, conservando idempotencia, intentos máximos y dead-letter queue. Para trabajos largos, devolver `202 Accepted` con un identificador consultable.

## 8. Integración con Vue

Los frontends mantienen su instancia Axios y usan:

```text
VITE_API_URL=http://localhost:8302/api
```

La vista pública llama a Laravel y muestra `ticket.verification`. Vue nunca llama directamente a SAM, por lo que no necesita otra URL, CORS ni el token interno. Ningún secreto debe llevar prefijo `VITE_`, porque Vite lo incorpora al JavaScript servido.

## 9. Estrategia de pruebas

1. Unitarias del handler y servicios con AWS SDK simulado.
2. `sam local invoke` con eventos versionados válidos, inválidos y límite.
3. `sam local start-api` para contrato HTTP, cabeceras, códigos y CORS.
4. Desde Laravel, incluyendo timeout y respuesta degradada si SAM no está disponible.
5. Desde Vue a través de Laravel.
6. Antes de producción, integración en una cuenta AWS de desarrollo.

Comandos automatizados incluidos:

```powershell
python -m unittest discover -s lambda/public-ticket-validation/tests -v
docker compose exec backend php artisan test
docker compose run --rm public-frontend npm run build
```

Comprobar idempotencia, cold start, payload, timeout, memoria, permisos mínimos, logs sin datos sensibles y dependencias caídas. La emulación no reproduce exactamente IAM, cuotas, latencia, VPC ni toda la conducta de API Gateway.

## 10. Cuándo incorporar LocalStack

No hace falta para la primera función HTTP. Incorporarlo cuando una función dependa de S3/SQS/SNS/DynamoDB y los mocks ya no validen el flujo. Se puede añadir entonces un perfil `aws-local` a Compose y configurar clientes SDK con endpoint local, región y credenciales ficticias.

- SAM valida empaquetado, handler, runtime y eventos Lambda/API Gateway.
- LocalStack valida la interacción local entre varios servicios AWS.

Las pruebas contra AWS siguen siendo obligatorias: los emuladores pueden diferir en IAM, cuotas, servicios nuevos y red.

## 11. Ruta incremental sugerida

1. Ejecutar la función y sus pruebas locales con SAM.
2. Añadir logs JSON, correlation ID y métricas de error/duración.
3. Sustituir el token compartido por IAM al desplegar en AWS.
4. Incorporar LocalStack solo con dependencias AWS múltiples.
5. Crear un stack de desarrollo y ejecutar pruebas reales antes de producción.

## Referencias

- [Pruebas locales con AWS SAM CLI](https://docs.aws.amazon.com/serverless-application-model/latest/developerguide/using-sam-cli-local.html)
- [`sam local start-api` y contenedores reutilizables](https://docs.aws.amazon.com/serverless-application-model/latest/developerguide/using-sam-cli-local-start-api.html)
- [API Gateway local, variables y proxy integration](https://docs.aws.amazon.com/serverless-application-model/latest/developerguide/serverless-sam-cli-using-start-api.html)
- [Recurso `AWS::Serverless::Function`](https://docs.aws.amazon.com/serverless-application-model/latest/developerguide/sam-resource-function.html)
- [Estrategias de prueba serverless](https://docs.aws.amazon.com/lambda/latest/dg/testing-guide.html)
- [Runtime Interface Emulator](https://docs.aws.amazon.com/lambda/latest/dg/python-image.html#python-image-instructions)
- [Lambda en LocalStack](https://docs.localstack.cloud/aws/services/lambda/)
