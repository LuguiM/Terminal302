# AGENTS.md — Frontend Terminal302

## Contexto del proyecto

Terminal302 es una plataforma web para la gestión de una terminal de buses.

Este archivo aplica únicamente al frontend del proyecto.

## Stack frontend

* Framework: Vue 3
* Bundler: Vite
* UI Framework: Vuetify
* Estado global: Pinia
* Cliente HTTP: Axios
* Rutas: Vue Router
* Estilos: Vuetify / CSS
* Entorno: Docker
* Backend esperado: Laravel API REST con Laravel Sanctum

## Estructura esperada

Mantener una estructura clara y modular dentro de `frontend/src`:

* `components/`: componentes reutilizables.
* `views/`: pantallas principales por módulo.
* `layouts/`: layouts generales si aplica.
* `router/`: configuración de rutas.
* `stores/`: stores de Pinia.
* `services/`: clientes HTTP y servicios de API.
* `plugins/`: configuración de Vuetify u otros plugins.
* `assets/`: imágenes, íconos y recursos estáticos.
* `composables/`: lógica reutilizable entre componentes.

## Reglas generales

* Mantener componentes pequeños, claros y reutilizables.
* Usar composables para lógica compartida.
* Usar Pinia cuando el estado sea compartido entre varias vistas o componentes.
* No usar Vuex; el manejador de estado oficial para este frontend será Pinia.
* No hardcodear URLs del API.
* Usar variables de entorno de Vite, por ejemplo `VITE_API_URL`.
* No colocar lógica de negocio compleja en el frontend; debe vivir en el backend.
* No duplicar llamadas HTTP; centralizarlas en servicios dentro de `src/services`.
* No subir `dist/`, `node_modules/` ni artefactos de build al repositorio.
* Mantener nombres de archivos, carpetas y componentes consistentes con la estructura existente.
* No eliminar archivos existentes importantes sin justificarlo.

## Vuetify

* Usar Vuetify como librería principal de componentes UI.
* Configurar Vuetify desde `src/plugins/vuetify.js` o `src/plugins/vuetify.ts`.
* Usar `v-app`, `v-main`, `v-container`, `v-card`, `v-btn`, `v-text-field`, `v-select`, `v-data-table` y demás componentes de Vuetify cuando aplique.
* Mantener un tema centralizado para Terminal302.
* No definir colores repetidos manualmente si ya existen en el tema de Vuetify.
* Preferir clases y utilidades de Vuetify antes de crear CSS innecesario.

## Responsive design

* Aunque los diseños base vienen de Figma en formato desktop, implementar con enfoque mobile first.
* Los estilos base deben funcionar en pantallas pequeñas.
* Adaptar progresivamente a tablet y desktop usando breakpoints.
* No usar anchos fijos innecesarios.
* Usar grillas responsivas de Vuetify.
* En desktop, el layout interno puede usar sidebar visible.
* En mobile, el sidebar debe convertirse en drawer temporal o colapsable.
* Las tablas deben ser responsivas mediante scroll horizontal, cards o una vista simplificada.
* Los formularios deben apilar campos en mobile y distribuirlos en columnas en desktop.

## Axios y comunicación con backend

* Usar Axios desde una instancia centralizada, por ejemplo `src/services/api.js` o `src/services/api.ts`.
* No usar `fetch` directamente en las vistas si ya existe el servicio Axios.
* La URL base del backend debe venir desde `VITE_API_URL`.
* Manejar errores HTTP de forma clara y mostrar mensajes amigables al usuario.
* Separar las llamadas por módulo cuando el proyecto crezca, por ejemplo:

  * `authService.js`
  * `userService.js`
  * `operatorService.js`
  * `routeService.js`
  * `busService.js`
  * `ticketService.js`

## Autenticación con Laravel Sanctum
* El backend usa autenticación mediante access_token Bearer.
* No usar cookies ni withCredentials para autenticación.
* Centralizar el header Authorization en la instancia Axios.
* Guardar el token como access_token.
* Las peticiones autenticadas deben enviar Authorization: Bearer <access_token>.
* El logout debe llamar al backend, limpiar el token local y redirigir a /login.

## Pinia y estado global

* Usar stores de Pinia solo cuando el estado sea compartido o persistente.
* Evitar guardar en Pinia datos que solo pertenecen a un componente específico.
* Mantener los stores pequeños y orientados a un dominio.
* Stores sugeridos para Terminal302:

  * `authStore`
  * `userStore`
  * `operatorStore`
  * `busStore`
  * `routeStore`
  * `scheduleStore`
  * `ticketStore`
  * `dashboardStore`

## Módulos esperados de Terminal302

El frontend debe prepararse para estos módulos principales:

* Autenticación
* Dashboard
* Gestión de usuarios y roles
* Registro de operadores
* Gestión de buses o unidades
* Gestión de rutas
* Gestión de horarios
* Venta de tickets
* Validación de tickets con QR
* Consulta pública de tickets
* Plantillas de ticket

## Docker

El frontend ya está dockerizado.

Antes de modificar dependencias o configuración, revisar:

* `Dockerfile`
* `docker-compose.yml`
* `package.json`
* `package-lock.json`
* `vite.config.js` o `vite.config.ts`
* `.env.example`

Reglas:

* No eliminar ni reemplazar la configuración Docker existente sin necesidad.
* Si se agregan dependencias, asegurar que el proyecto siga funcionando dentro del contenedor.
* Si se modifica `package.json`, verificar si también debe actualizarse `package-lock.json`.
* Si se agregan variables de entorno, actualizar `.env.example`.
* Mantener compatibilidad para levantar el frontend localmente y desde Docker.
* No hardcodear nombres de host o puertos del backend si deben venir desde variables de entorno.

## Rutas

* Usar Vue Router si ya está instalado o si el proyecto lo requiere.
* Separar rutas por módulos cuando crezca el proyecto.
* Proteger rutas privadas con guards de autenticación.
* Mantener rutas públicas separadas de las rutas internas del sistema.
* La consulta pública de tickets debe poder accederse sin autenticación.

## Ruta de inicio

* La ruta `/inicio` es una ruta interna fija del frontend.
* `/inicio` no vendrá desde el menú del backend.
* Debe estar disponible para cualquier usuario autenticado.
* Debe mostrarse siempre en el sidebar como opción fija.
* Debe usar `meta.skipMenuPermission: true` para no validarse contra `allowedRoutes`.
* `/` debe redirigir a `/inicio`.

## Calidad y mantenimiento

* Mantener código limpio, legible y fácil de extender.
* Evitar componentes demasiado grandes.
* Evitar duplicación de lógica.
* Añadir validaciones básicas en formularios.
* Mostrar estados de carga cuando una vista dependa de datos del backend.
* Mostrar estados vacíos cuando no existan datos.
* Mostrar errores de forma amigable.
* Verificar accesibilidad básica en formularios, botones y navegación.
* Mantener compatibilidad con navegadores modernos.

## Testing

* Agregar pruebas unitarias para componentes críticos cuando aplique.
* Priorizar pruebas para:

  * Login
  * Guards de rutas
  * Formularios principales
  * Componentes reutilizables
  * Servicios de API importantes

## Build y deployment

* Usar `npm run build` para generar producción.
* Verificar que el build no falle antes de cerrar cambios importantes.
* No subir la carpeta `dist/`.
* Usar variables de entorno adecuadas para cada ambiente.
* Confirmar que el frontend pueda conectarse correctamente al backend desde Docker.

## Antes de modificar código

Antes de realizar cambios, el agente debe:

1. Revisar la estructura actual dentro de `frontend`.
2. Revisar `package.json`.
3. Revisar configuración de Vite.
4. Revisar configuración Docker existente.
5. Identificar si el proyecto usa JavaScript o TypeScript.
6. Indicar qué archivos se crearán o modificarán.
7. Mantener las convenciones existentes.
8. Hacer cambios mínimos y justificados.
9. Verificar que el proyecto compile.
10. Indicar los comandos necesarios para probar los cambios.

## Al finalizar una tarea

El agente debe responder con:

* Archivos creados.
* Archivos modificados.
* Dependencias instaladas o modificadas.
* Comandos que debe ejecutar el usuario.
* Advertencias importantes si aplica.
* Siguiente paso recomendado.
