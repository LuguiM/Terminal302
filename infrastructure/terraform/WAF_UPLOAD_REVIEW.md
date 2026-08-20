# Informe de corrección AWS WAF para uploads

**Proyecto:** Terminal302  
**Fecha:** 2026-08-20  
**Alcance:** AWS WAF, endpoints HTTP de carga de archivos y validaciones relacionadas  
**Estado:** Implementación local validada; `terraform plan` pendiente por credenciales AWS

## 1. Resumen ejecutivo

AWS WAF presentó dos falsos positivos confirmados sobre la misma carga legítima
de plantillas. Primero `SizeRestrictions_BODY` bloqueó el multipart por superar
8,192 bytes. Después de poner esa regla en `Count`, la evidencia de WAF mostró
que `CrossSiteScripting_BODY` detectaba contenido binario del mismo archivo y
terminaba la evaluación con `Block`. Laravel y S3 seguían sin recibir el request.

Se modificó exclusivamente la Web ACL para:

- mantener AWS WAF habilitado;
- mantener `AWSManagedRulesCommonRuleSet` y todas sus demás reglas;
- poner únicamente `SizeRestrictions_BODY` y `CrossSiteScripting_BODY` en
  `Count`, ambas con falso positivo demostrado;
- volver a bloquear cualquiera de sus labels en todos los demás requests;
- omitir esos bloqueos sólo para los uploads reales de plantillas;
- conservar las validaciones, autenticación y autorización de Laravel.

No se modificaron recursos AWS, permisos S3, contratos API ni lógica funcional.
No se ejecutó `terraform apply`.

## 2. Inventario de endpoints de upload

| Método HTTP observado por WAF | Endpoint | Controller/acción | Clasificación | Tipo permitido | Tamaño máximo | Autenticación | Rol |
|---|---|---|---|---|---:|---|---|
| `POST` | `/api/admin/ticket-plantillas` | `AdminTicketPlantillaController::store` | Imagen | Regla Laravel `image`; frontend PNG/JPG/JPEG | 10,240 KB (10 MB) | Sanctum y contraseña inicial cambiada | administrador |
| `POST` con `_method=PUT` | `/api/admin/ticket-plantillas/{id}` | `AdminTicketPlantillaController::update` | Imagen opcional | Regla Laravel `image`; frontend PNG/JPG/JPEG | 10,240 KB (10 MB) | Sanctum y contraseña inicial cambiada | administrador |

La ruta Laravel de actualización está declarada como `PUT`, pero el frontend
envía un `POST multipart/form-data` con `_method=PUT`. AWS WAF observa el método
HTTP exterior `POST`, antes de que Laravel aplique el method spoofing.

No se encontraron otros endpoints HTTP que reciban archivos.

### Elementos descartados correctamente del inventario

- El selector de imagen del frontend público procesa códigos QR localmente con
  `Html5Qrcode`; no envía la imagen al backend.
- `TicketRenderService` y otros servicios escriben objetos generados
  internamente con `Storage::put`; no son uploads HTTP.
- Los endpoints de descarga y las URLs prefirmadas no reciben archivos.

## 3. Validaciones de aplicación

Los dos Form Requests aplican:

- `image` para comprobar que el contenido sea una imagen válida;
- `max:10240`, configurable mediante `TICKET_TEMPLATE_MAX_SIZE_KB`;
- dimensiones exactas configurables, actualmente 1000 × 500 píxeles;
- prohibición de que el cliente proporcione `estado_id` o `image_path`.

La creación exige la imagen. En la actualización es opcional.

El frontend limita el selector a:

- `image/png`;
- `image/jpeg`;
- extensiones `.png`, `.jpg` y `.jpeg`.

El backend no contiene una regla explícita `mimes`; la regla `image` puede
aceptar otros formatos de imagen compatibles con Laravel. Esto no permite
archivos arbitrarios, pero constituye una diferencia entre la interfaz y el
contrato efectivo del backend. No se cambió porque no era necesario para
resolver el bloqueo WAF ni existe una decisión funcional que restrinja el
backend exclusivamente a PNG/JPEG.

La capa de transporte permite un margen suficiente sobre el límite funcional:

- Nginx: `client_max_body_size 12m`;
- PHP: `post_max_size=12M`;
- PHP: `upload_max_filesize=12M`.

## 4. Causa del bloqueo

La Web ACL ejecutaba `AWSManagedRulesCommonRuleSet` sin excepciones. Dentro de
ese grupo, `SizeRestrictions_BODY` bloquea cuerpos superiores a 8 KB y produce
el label:

```text
awswaf:managed:aws:core-rule-set:SizeRestrictions_Body
```

Flujo anterior:

```text
Cliente
  → AWS WAF
  → SizeRestrictions_BODY: BLOCK
  → ALB no recibe el request
  → Laravel no valida
  → S3 no recibe objetos
```

Tras la primera corrección, WAF confirmó esta secuencia:

```text
SizeRestrictions_BODY       → COUNT
CrossSiteScripting_BODY     → BLOCK
```

El segundo 403 también era anterior a Laravel y no estaba relacionado con IAM,
S3 ni validaciones de aplicación. El label oficial de la segunda regla es:

```text
awswaf:managed:aws:core-rule-set:CrossSiteScripting_Body
```

## 5. Solución Terraform implementada

Archivo: `infrastructure/terraform/waf.tf`.

### 5.1 Override individual

Sólo las reglas internas `SizeRestrictions_BODY` y
`CrossSiteScripting_BODY` se cambiaron a `Count` mediante overrides
individuales. El resto de `AWSManagedRulesCommonRuleSet` conserva sus acciones
administradas.

### 5.2 Restauración del bloqueo general

La regla posterior de prioridad 11 ahora se denomina:

```text
BlockManagedBodyMatchesExceptTicketTemplateUploads
```

Esta única regla consume mediante OR los labels de `SizeRestrictions_BODY` y
`CrossSiteScripting_BODY`, y aplica `Block` salvo cuando la solicitud coincide
con la excepción de upload. Combinar los labels permite reutilizar una sola vez
el bloque que identifica el upload y evita dos reglas extensas duplicadas.

### 5.3 Condiciones acumulativas de la excepción

Todas deben cumplirse simultáneamente:

1. Método HTTP exterior exactamente `POST`.
2. `Content-Type` comienza con `multipart/form-data`.
3. `Authorization` comienza con `Bearer `.
4. URI coincide con:

   ```text
   ^/api/admin/ticket-plantillas(/[0-9]+)?$
   ```

Esto cubre exclusivamente:

- creación: `/api/admin/ticket-plantillas`;
- actualización: `/api/admin/ticket-plantillas/{id numérico}`.

No se creó un `Allow`. Los requests exceptuados continúan recorriendo las demás
reglas WAF y luego deben superar autenticación, rol y validaciones Laravel. La
comprobación del header Bearer sólo reduce el alcance de WAF; Laravel sigue
siendo quien valida realmente el token.

### 5.4 Comportamiento resultante

| Solicitud | Resultado WAF de las dos reglas BODY |
|---|---|
| Upload válido en uno de los dos paths exactos | Los matches se cuentan; la regla posterior no bloquea |
| POST multipart a otro endpoint | Cualquiera de los dos labels vuelve a producir `Block` |
| Request sin Bearer a los paths de plantillas | No aplica la excepción; los labels vuelven a bloquear |
| Request no multipart a los paths de plantillas | No aplica la excepción |
| Request con path no numérico después de plantillas | No aplica la excepción |

## 6. Protecciones mantenidas

Continúan activas:

- reglas XSS sobre query string, cookies y URI con su acción administrada;
- `CrossSiteScripting_BODY` con bloqueo restaurado fuera de los uploads exactos;
- reglas LFI/path traversal;
- reglas RFI;
- reglas SSRF hacia metadatos EC2;
- restricciones de URI, query string, cookies y extensiones peligrosas;
- `AWSManagedRulesKnownBadInputsRuleSet`;
- rate limiting de 1,000 solicitudes por IP;
- `SizeRestrictions_BODY` con bloqueo restaurado fuera de la excepción exacta;
- autenticación Sanctum;
- middleware de cambio de contraseña inicial;
- autorización por rol administrador;
- validaciones MIME/contenido, tamaño y dimensiones en Laravel.

AWS WAF para ALB sólo puede inspeccionar los primeros 8 KB del body para varias
reglas administradas. Esta es una limitación del servicio y no se modifica con
la excepción.

### Revisión de reglas BODY del Common Rule Set

| Regla | Inspecciona BODY | Acción efectiva actual | Riesgo para multipart | Modificar ahora |
|---|---|---|---|---|
| `SizeRestrictions_BODY` | Sí | `Count` en managed group; `Block` posterior fuera de la excepción | Alto y confirmado: todo multipart de más de 8 KB coincide | Sí, ya implementado |
| `EC2MetaDataSSRF_BODY` | Sí, primeros 8 KB | `Block` administrado | Bajo: una secuencia binaria o campo podría parecer una URL de metadata, sin evidencia en este upload | No |
| `GenericLFI_BODY` | Sí, primeros 8 KB | `Block` administrado | Bajo/moderado: podría detectar secuencias de traversal dentro del multipart, sin evidencia confirmada | No |
| `GenericRFI_BODY` | Sí, primeros 8 KB | `Block` administrado | Bajo/moderado: podría detectar URLs remotas dentro del multipart, sin evidencia confirmada | No |
| `CrossSiteScripting_BODY` | Sí, primeros 8 KB | `Count` en managed group; `Block` posterior fuera de la excepción | Alto y confirmado por el evento WAF del mismo archivo | Sí, implementado en esta revisión |

No se aplicaron overrides preventivos a SSRF, LFI o RFI. Si WAF aporta evidencia
futura de otro falso positivo, debe evaluarse individualmente utilizando su
label oficial y la misma excepción exacta, no mediante una exclusión global.

### Labels consumidos

```text
SizeRestrictions_BODY
→ awswaf:managed:aws:core-rule-set:SizeRestrictions_Body

CrossSiteScripting_BODY
→ awswaf:managed:aws:core-rule-set:CrossSiteScripting_Body
```

El Common Rule Set conserva prioridad 10 y crea ambos labels con acciones no
terminantes `Count`. La regla restauradora tiene prioridad 11, por lo que puede
consumirlos. `KnownBadInputs` permanece en prioridad 20 y el rate limit en 30;
no existen prioridades duplicadas.

### Hallazgo independiente: SQL injection

La Web ACL actual no incluye `AWSManagedRulesSQLiRuleSet`. Por ello no existe una
protección WAF dedicada contra SQL injection que pueda confirmarse como activa.
Este es un gap previo e independiente de la corrección de uploads. Añadir ese
grupo debe evaluarse como un cambio separado, con pruebas de falsos positivos y
capacidad WCU.

## 7. S3 e IAM

No se realizaron cambios en S3 ni IAM porque el error ocurre antes del ALB.
Permanecen:

- bucket privado;
- bloqueo completo de acceso público;
- cifrado SSE-S3;
- versioning;
- acceso mediante ECS Task Role;
- operaciones `GetObject`, `PutObject` y `DeleteObject` de Laravel;
- URLs prefirmadas sin credenciales estáticas.

## 8. Archivos modificados

| Archivo | Cambio |
|---|---|
| `infrastructure/terraform/waf.tf` | Overrides individuales de tamaño/XSS, excepción mínima compartida y restauración del bloqueo general |
| `infrastructure/terraform/ecs.tf` | Sólo ajuste de espacios requerido por `terraform fmt`; sin cambio funcional |
| `infrastructure/terraform/WAF_UPLOAD_REVIEW.md` | Este informe |

No se modificaron controllers, rutas, Form Requests, frontend, S3, IAM, RDS,
VPC, ALB ni contratos API.

## 9. Validaciones realizadas

### Terraform

```text
terraform fmt -check -recursive: PASS
terraform validate:             PASS
```

Las comprobaciones se ejecutaron con Terraform 1.6.6 mediante la imagen oficial
de Docker.

### Backend

```text
Tests\Feature\TicketPlantillaApiTest
12 tests passed
98 assertions
```

Estas pruebas validan creación, actualización, almacenamiento, límite de tamaño,
dimensiones, descarga, autorización y reglas de seguridad de plantillas.

## 10. Estado de `terraform plan`

El plan no pudo generarse porque el contenedor no encontró una fuente válida de
credenciales AWS al reinicializar el backend S3:

```text
No valid credential sources found
```

Terraform se detuvo antes de consultar el state. No se creó `waf-review.plan`,
no se ejecutó `apply` y no se modificaron recursos AWS.

Por la estructura del cambio, el resultado esperado es una actualización
in-place de la Web ACL:

```text
Esperado, no confirmado: 0 to add, 1 to change, 0 to destroy
```

Este resumen no debe tratarse como resultado real hasta ejecutar el plan con
credenciales válidas. Si aparecieran cambios en RDS, S3, ECS, VPC o ALB, no se
debe aplicar el plan hasta explicar su origen.

## 11. Checklist posterior al apply

### 11.1 Crear plantilla válida

1. Autenticarse como administrador.
2. Enviar PNG/JPEG de 1000 × 500 y menor de 10 MB a
   `POST /api/admin/ticket-plantillas`.
3. Confirmar que WAF permite el request.
4. Confirmar respuesta HTTP 201.
5. Confirmar registro en PostgreSQL y objeto en S3.

### 11.2 Actualizar plantilla

1. Enviar desde el frontend un POST multipart con `_method=PUT` a
   `/api/admin/ticket-plantillas/{id}`.
2. Confirmar respuesta HTTP 200.
3. Confirmar que la imagen nueva existe y que la anterior se elimina cuando no
   está referenciada.

### 11.3 Archivo demasiado grande

1. Enviar una imagen superior a 10 MB pero dentro del límite de transporte de
   12 MB.
2. Confirmar que WAF permite llegar a Laravel.
3. Confirmar respuesta HTTP 422 por `image.max`.
4. Confirmar que no se crea objeto S3.

### 11.4 MIME no permitido

1. Renombrar un ejecutable como `.jpg`.
2. Enviarlo al endpoint de creación.
3. Confirmar respuesta HTTP 422 por la regla `image`.
4. Confirmar que no se crea objeto S3.

### 11.5 Endpoint normal y XSS fuera del upload

1. Enviar un body superior a 8 KB a un endpoint que no sea de upload.
2. Confirmar el label `SizeRestrictions_Body` y el bloqueo posterior.
3. Enviar un patrón XSS de prueba no destructivo a otro endpoint.
4. Confirmar el label `CrossSiteScripting_Body`.
5. Confirmar el bloqueo de
   `BlockManagedBodyMatchesExceptTicketTemplateUploads`.

### 11.6 Archivo con el falso positivo XSS confirmado

1. Repetir exactamente el archivo que generó el evento confirmado.
2. Confirmar `SizeRestrictions_BODY = Count`.
3. Confirmar `CrossSiteScripting_BODY = Count` si vuelve a coincidir.
4. Confirmar que la regla posterior no bloquea porque se cumplen las cuatro
   condiciones exactas.
5. Confirmar HTTP 201 y objeto S3.

### 11.7 Excepción incompleta

1. Repetir el multipart sin header Bearer y confirmar que no se exceptúa.
2. Enviar el mismo request a `/api/admin/ticket-plantillas/test` y confirmar que
   no se exceptúa porque el identificador no es numérico.

### 11.8 Payload malicioso

1. Probar patrones XSS, path traversal y known-bad en los endpoints de upload.
2. Confirmar que las demás reglas administradas bloquean cuando corresponde.
3. Revisar sampled requests y métricas WAF.

Métrica nueva:

```text
<project>-<environment>-managed-body-block
```

## 12. Próximo paso seguro

Después de configurar una fuente válida de credenciales AWS:

```powershell
Set-Location infrastructure/terraform
terraform fmt -check -recursive
terraform validate
terraform plan -out=waf-review.plan
terraform show -no-color waf-review.plan
```

Revisar que el plan contenga únicamente la actualización in-place de
`aws_wafv2_web_acl.main[0]`. No ejecutar `terraform apply` hasta aprobar
manualmente ese resultado.

## 13. Referencias técnicas

- [AWS Managed Rules: Core Rule Set y labels oficiales](https://docs.aws.amazon.com/waf/latest/developerguide/aws-managed-rule-groups-baseline.html)
- [Funcionamiento de labels en AWS WAF](https://docs.aws.amazon.com/waf/latest/developerguide/waf-rule-label-overview.html)
- [Overrides de reglas en grupos administrados](https://docs.aws.amazon.com/waf/latest/developerguide/web-acl-rule-group-settings.html)
- [Esquema Terraform de `aws_wafv2_web_acl`](https://registry.terraform.io/providers/hashicorp/aws/latest/docs/resources/wafv2_web_acl)
