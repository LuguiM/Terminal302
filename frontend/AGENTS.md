# AGENTS.md — Frontend (Terminal302)

## Contexto y stack (Frontend)

- Framework: Vue 3
- Bundler: Vite
- Estructura: `src/`, `components/`, `assets/`, `views/`
- Estilos: CSS / Tailwind (si aplica)

## Reglas generales (Frontend)

- Mantener componentes pequeños y reutilizables.
- Usar composables para lógica compartida.
- No hardcodear URLs del API; obtener desde variables de entorno (`.env`) o configuración central.
- Evitar construir lógica de negocio en el frontend; delegar al backend.
- No subir `dist/` ni artefactos de build al repositorio.

## Comunicación con el backend

- Consumir la API REST del backend (Laravel Sanctum para autenticación).
- Para autenticación con Sanctum en SPA, usar cookies y configurar CORS/credentials apropiadamente.
- Manejar errores HTTP y mostrar mensajes amigables al usuario.

## Estado y datos

- Preferir llamadas puntuales para datos simples.
- Para datos compartidos o complejos, usar un store (Pinia/Vuex) si está justificado.

## Testing y calidad

- Añadir pruebas unitarias para componentes críticos.
- Verificar accesibilidad básica y compatibilidad en navegadores modernos.

## Deployment y builds

- Usar `npm run build` o `yarn build` para generar la versión de producción.
- Asegurarse de usar variables de entorno apropiadas para la URL del API.

## Antes de modificar código (Checklist)

1. Revisar la estructura del frontend (`frontend/src`).
2. Indicar qué archivos se crearán o modificarán.
3. Mantener las convenciones y estilos existentes.
