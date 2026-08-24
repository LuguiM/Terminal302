# Entrega digital asincrona con S3 y Lambda

## 1. Flujo anterior

La venta se confirma en `VendedorTicketController::store`. Laravel crea uno o
varios `Ticket` en una transaccion, con estado funcional `Emitido` y, para el
tipo de envio digital, estado de procesamiento `Pendiente`.

Despues de confirmar la transaccion, `TicketRenderService` genera el QR en
`ticket-qrs/` y la imagen PNG final en `tickets/final/`. A continuacion,
`TicketProcessingEventService` escribia un JSON amplio en
`ticket-events/pending/<codigo_ticket>.json` y guardaba la ruta en
`tickets.processing_event_path`.

No habia consumidor S3. El operador debia ejecutar:

```shell
php artisan tickets:process-digital-deliveries
php artisan tickets:process-digital-deliveries --limit=10
```

El comando delegaba en `TicketDigitalDeliveryService`, que listaba los JSON de
`pending`, cargaba el ticket, cambiaba su estado a `Procesando`, enviaba
`DigitalTicketMail` con el PNG adjunto y finalmente movia el JSON a `completed`.
Ante una excepcion movia el JSON a `failed`, asignaba el estado `Fallido` y
guardaba `processing_error`. El vendedor podia publicar de nuevo un evento para
un ticket Pendiente o Fallido desde `POST /api/vendedor/tickets/{id}/retry-processing`.

La generacion de imagen y QR siempre ocurrio en Laravel/ECS. El placeholder de
WhatsApp no envia mensajes ni mantiene estados adicionales.

## 2. Flujo nuevo

```text
Laravel / ECS
  -> S3 ticket-events/pending/<uuid>.json
  -> S3 ObjectCreated (solo prefix pending y suffix .json)
  -> Lambda digital-ticket-processor
  -> POST /api/internal/digital-ticket-deliveries/process
  -> TicketDigitalDeliveryService
  -> correo mediante DigitalTicketMail
  -> Lambda archiva el JSON en completed o failed
```

Laravel sigue siendo propietario de todas las reglas de negocio, acceso a
PostgreSQL, estados y correo. La Lambda no genera tickets, no usa SMTP y no se
conecta a PostgreSQL. `public-ticket-validation` permanece separada y sin
cambios.

La modificacion minima consistio en separar la orquestacion S3 del procesamiento
existente, crear un endpoint de maquina y hacer atomica la reclamacion de un
evento. El comando Artisan se conserva como fallback/reprocesamiento manual y
usa el mismo servicio.

## 3. Contrato del evento

Ruta:

```text
ticket-events/pending/<event_id UUID v4>.json
```

Esquema version 1:

```json
{
  "schema_version": 1,
  "event_id": "123e4567-e89b-42d3-a456-426614174000",
  "ticket_id": 42,
  "created_at": "2026-08-20T18:00:00Z"
}
```

El nombre del objeto y `event_id` deben coincidir. El payload no contiene email,
telefono, codigo de ticket, rutas de archivos, tokens, passwords ni datos del
pasajero. Laravel obtiene lo necesario desde el modelo `Ticket`.

## 4. Lambda

`lambda/digital-ticket-processor/src/handler.py` usa Python 3.13 y bibliotecas
incluidas en el runtime. Soporta multiples `Records`, decodifica keys con formato
URL, valida bucket/prefix/suffix y procesa cada registro por separado.

Por cada evento valido:

1. Lee y valida el JSON.
2. Obtiene el token de maquina desde Secrets Manager, con cache en memoria.
3. Llama al endpoint Laravel con solo `event_id`.
4. Reintenta respuestas 409/5xx y fallos de red hasta tres veces, con backoff
   acotado.
5. Escribe un JSON de resultado en `completed` o `failed` y despues elimina el
   objeto `pending`.

Si el backend sigue sin responder despues de los reintentos internos, el evento
se archiva en `failed`; los registros que ya terminaron no se repiten. Una notificacion
duplicada cuyo objeto `pending` ya no existe se trata como completada previamente.

## 5. S3 Trigger

Terraform configura exactamente:

```text
event:  s3:ObjectCreated:*
prefix: ticket-events/pending/
suffix: .json
```

Los objetos escritos en `ticket-templates/`, `tickets/`, `ticket-qrs/`,
`ticket-events/completed/` y `ticket-events/failed/` no invocan la funcion. La
Lambda nunca escribe bajo `pending`, por lo que no existe recursion. El bucket
existente no se reemplaza; `aws_s3_bucket_notification` referencia el recurso ya
administrado por Terraform y depende del permiso S3 -> Lambda.

## 6. Idempotencia

`processing_event_path` identifica el evento vigente de cada ticket. Antes de
publicar el objeto, Laravel guarda la nueva ruta UUID en el ticket para cerrar la
carrera entre la escritura S3 y la notificacion.

`TicketDigitalDeliveryService` reclama el trabajo mediante un `UPDATE`
condicional que exige simultaneamente:

- el mismo `ticket_id`;
- el mismo `processing_event_path`;
- el estado de procesamiento Pendiente observado.

Solo un consumidor puede cambiarlo a Procesando. Los demas reciben Procesando,
Completado o Fallido y no envian correo. Un evento viejo no puede modificar un
reintento nuevo. Si la respuesta HTTP se pierde despues de completar, el retry
consulta el estado Completado y archiva el objeto sin reenviar el email.

SMTP no ofrece una transaccion distribuida con PostgreSQL. Si el proceso muere
exactamente despues de aceptar el correo y antes de guardar Completado, el ticket
queda en Procesando deliberadamente: no se reenvia automaticamente porque no se
puede saber con seguridad si el proveedor acepto el mensaje. Ese caso requiere
reconciliacion administrativa antes de marcar Fallido y publicar un evento nuevo.

## 7. Errores y reintentos

Los fallos funcionales (ticket inexistente/no digital, email ausente, imagen
ausente o error al enviar) terminan en estado Fallido y objeto `failed/<uuid>.json`.
El archivo archivado contiene el payload minimo y resultado; un JSON invalido se
archiva con diagnostico sin copiar su contenido potencialmente sensible.

Los fallos HTTP transitorios se reintentan tres veces dentro de la Lambda. Si se
agotan, el objeto se archiva en `failed` con un diagnostico sin secretos. Los
fallos no controlados de infraestructura S3/Secrets/Lambda conservan `pending` y
`aws_lambda_function_event_invoke_config` permite dos reintentos asincronos de
AWS, con edad maxima de una hora. No hay loops infinitos. Los eventos pendientes
pueden inspeccionarse o procesarse con el comando Artisan. Los reintentos funcionales explicitos siguen
usando el endpoint del vendedor existente, que crea un UUID nuevo.

## 8. Seguridad e IAM

El endpoint interno es `POST /api/internal/digital-ticket-deliveries/process`.
No usa Sanctum ni identidad de usuario final. El middleware exige
`X-Internal-Token` y compara con `hash_equals`; el body acepta unicamente
`event_id` validado como UUID.

El secreto JSON indicado por `app_secret_arn` debe incorporar una clave nueva e
independiente llamada `DIGITAL_DELIVERY_INTERNAL_TOKEN`. ECS recibe esa clave
como secret; Lambda solo recibe el ARN y la lee mediante Secrets Manager. El
token no se incluye en S3, Terraform, logs ni respuestas.

IAM de la Lambda permite solamente:

- escribir streams/eventos en su log group;
- `GetObject` y `DeleteObject` en `ticket-events/pending/*`;
- `PutObject` en `ticket-events/completed/*` y `ticket-events/failed/*`;
- `GetSecretValue` sobre `app_secret_arn`.

No hay acciones ni recursos comodin globales.

La llamada usa el ALB publico existente para evitar crear otro balanceador o
reestructurar la VPC. Terraform impide habilitar la Lambda sin HTTPS, evitando
enviar el token por texto claro. WAF tambien evalua este endpoint: el JSON pequeno
no requiere excepcion de reglas managed y no se agrego ninguna. La regla general
de rate limit puede producir 429 en una rafaga extraordinaria; no consume el
evento y deja que opere el retry asincrono de AWS, por lo que se debe vigilar antes de elevar
volumen. Una evolucion razonable, si el trafico lo exige, seria una regla WAF
acotada a path/metodo con un limite propio, no una exclusion general.

## 9. Observabilidad

Terraform crea:

```text
/aws/lambda/terminal302-production-digital-ticket-processor
```

La retencion usa `log_retention_days`. Los logs JSON incluyen, cuando existen,
`event_id`, `ticket_id`, `delivery_id` (actualmente igual a ticket), `status`,
`duration_ms`, `error_type` y request id. No registran headers, token, email,
telefono ni contenido del ticket. Laravel registra path, ticket id y clase de
error, sin credenciales.

## 10. Componentes y archivos

Backend nuevos:

- `InternalDigitalTicketDeliveryController.php`;
- `ProcessDigitalTicketDeliveryRequest.php`;
- `EnsureDigitalDeliveryMachineToken.php`;
- `config/digital_delivery.php`.

Backend modificados:

- `TicketProcessingEventService.php`;
- `TicketDigitalDeliveryService.php`;
- `ProcesamientoEstado.php`;
- `bootstrap/app.php`, `routes/api.php`, `.env.example`;
- `tests/Feature/TicketApiTest.php`.

Lambda:

- `lambda/digital-ticket-processor/src/handler.py`;
- `lambda/digital-ticket-processor/tests/test_handler.py`.

Infraestructura:

- `infrastructure/terraform/lambda.tf`;
- `infrastructure/terraform/ecs.tf`;
- `infrastructure/terraform/variables.tf`;
- `infrastructure/terraform/outputs.tf`;
- `infrastructure/terraform/terraform.tfvars.example`;
- `infrastructure/template.yaml` solo para desarrollo local;
- `.env.production.example`.

No se agrego migracion ni dependencia nueva.

## 11. Pruebas y validacion

Resultados ejecutados el 20 de agosto de 2026:

- Laravel: 191 pruebas, 1412 assertions, todas correctas.
- Lambda Python 3.13: 9 pruebas, todas correctas.
- `terraform fmt -check -recursive`: correcto.
- `terraform validate`: configuracion valida.
- validacion SAM basica: template valido.

La cobertura nueva incluye evento S3 valido, key fuera de `pending`, JSON
invalido, notificacion duplicada, entrega ya completada/fallida, fallo del
backend, retry acotado, multiples records, envio exitoso y transiciones a
`completed`/`failed`.

Se intento `terraform plan`, pero el runtime no encontro una fuente valida de
credenciales AWS y no pudo reinicializar el backend S3. Por tanto no existe un
plan actualizado con el que afirmar cambios reales del estado. No se ejecuto
`terraform apply` ni se modifico ningun recurso AWS. Las referencias nuevas no
expresan reemplazo de RDS, bucket S3, ECS, ALB, VPC o Route 53, pero esto debe
confirmarse con el plan autenticado antes del despliegue.

## 12. Despliegue

1. Agregar `DIGITAL_DELIVERY_INTERNAL_TOKEN` con un valor aleatorio fuerte al
   secreto JSON existente de `app_secret_arn`. No cambiar
   `LAMBDA_INTERNAL_TOKEN`, usado por la Lambda publica.
2. Publicar una imagen backend que incluya el endpoint y desplegar primero la
   nueva revision ECS. Verificar salud y una llamada no autorizada con 401.
3. Desde `infrastructure/terraform`, ejecutar `terraform init` con el backend S3
   aprobado, `terraform fmt -check -recursive`, `terraform validate` y
   `terraform plan -out=async-digital-delivery.plan`.
4. Revisar `terraform show async-digital-delivery.plan`. Debe agregar Lambda,
   rol/politica, log group, permiso, invoke config y notification; debe actualizar
   la task definition backend por el secret nuevo. No debe destruir RDS, S3,
   ECS, ALB, VPC ni Route 53.
5. Solo despues de aprobacion operativa, aplicar ese plan fuera de este trabajo.
6. Crear una venta digital de prueba y verificar logs, un solo correo, ausencia
   de `pending/<uuid>.json`, existencia de `completed/<uuid>.json` y estado
   Completado en Laravel.
7. Mantener disponible `tickets:process-digital-deliveries` como herramienta de
   contingencia.

## 13. Rollback

1. Establecer `enable_digital_ticket_processor=false`, generar y revisar un
   plan. Esto retira notification, permiso y Lambda sin tocar el bucket ni sus
   objetos.
2. Aplicar el plan aprobado y volver a procesar `pending` con el comando Artisan.
3. Si tambien se revierte backend, hacerlo despues de retirar el trigger. La
   version anterior no entiende el esquema UUID versionado; para eventos ya
   creados se recomienda conservar temporalmente el backend nuevo o reprocesar
   administrativamente los tickets, no renombrar/copiar objetos a ciegas.
4. Conservar los objetos `completed`/`failed` y logs durante la investigacion.
   No eliminar el bucket, tickets, imagenes ni estados de base de datos.
