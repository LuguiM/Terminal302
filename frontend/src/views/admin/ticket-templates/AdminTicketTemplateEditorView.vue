<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import PageTitle from '@/components/common/PageTitle.vue'
import { notify } from '@/services/notifyService'
import {
  getAdminTicketTemplateImageObjectUrl,
  getAdminTicketTemplate,
  updateAdminTicketTemplate,
} from '@/services/adminTicketTemplateService'

const route = useRoute()
const router = useRouter()

const template = ref(null)
const templateImageUrl = ref('')
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const canvasRef = ref(null)
const scale = ref(1)
const selectedKey = ref('')
const dragState = ref(null)
const resizeState = ref(null)

const templateWidth = Number(import.meta.env.VITE_TICKET_TEMPLATE_WIDTH ?? 1000)
const templateHeight = Number(import.meta.env.VITE_TICKET_TEMPLATE_HEIGHT ?? 500)

const elementDefinitions = [
  {
    key: 'operador_location',
    label: 'Operador de transporte',
    sample: 'Operador de transporte',
    type: 'text',
    defaultWidth: 220,
    defaultHeight: 32,
  },
  {
    key: 'codigo_ticket_location',
    label: 'Numero de ticket',
    sample: 'TKT-000001',
    type: 'text',
    defaultWidth: 180,
    defaultHeight: 32,
  },
  {
    key: 'ruta_location',
    label: 'Ruta',
    sample: 'Ruta 301-A',
    type: 'text',
    defaultWidth: 150,
    defaultHeight: 32,
  },
  {
    key: 'asiento_location',
    label: 'Asiento',
    sample: 'Asiento 12',
    type: 'text',
    defaultWidth: 140,
    defaultHeight: 32,
  },
  {
    key: 'salida_location',
    label: 'Salida',
    sample: 'Salida 08:30 AM',
    type: 'text',
    defaultWidth: 180,
    defaultHeight: 32,
  },
  {
    key: 'fecha_hora_location',
    label: 'Fecha y hora',
    sample: '15/05/2026 08:30 AM',
    type: 'text',
    defaultWidth: 240,
    defaultHeight: 32,
  },
  {
    key: 'qr_location',
    label: 'Codigo QR',
    sample: 'QR',
    type: 'qr',
    defaultWidth: 120,
    defaultHeight: 120,
  },
  {
    key: 'precio_location',
    label: 'Precio',
    sample: '$2.50',
    type: 'text',
    defaultWidth: 130,
    defaultHeight: 32,
  },
]

const locations = reactive({})

let resizeObserver = null

const revokeTemplateImageUrl = () => {
  if (templateImageUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(templateImageUrl.value)
  }

  templateImageUrl.value = ''
}

const loadTemplateImage = async (id) => {
  revokeTemplateImageUrl()
  templateImageUrl.value = await getAdminTicketTemplateImageObjectUrl(id)
}

const selectedElement = computed(() =>
  elementDefinitions.find((element) => element.key === selectedKey.value),
)

const selectedLocation = computed(() => (
  selectedKey.value ? locations[selectedKey.value] : null
))

const visibleElements = computed(() =>
  elementDefinitions
    .filter((element) => locations[element.key])
    .map((element) => ({
      ...element,
      location: locations[element.key],
    })),
)

const setScale = () => {
  if (!canvasRef.value) {
    scale.value = 1
    return
  }

  scale.value = canvasRef.value.clientWidth / templateWidth
}

const normalizeLocation = (element, location = {}) => ({
  x: Number(location.x ?? 24),
  y: Number(location.y ?? 24),
  width: Number(location.width ?? element.defaultWidth),
  height: Number(location.height ?? element.defaultHeight),
  font_size: Number(location.font_size ?? 18),
  color: location.color ?? '#001233',
  align: location.align ?? 'center',
  font_weight: location.font_weight ?? 800,
})

const loadLocations = () => {
  elementDefinitions.forEach((element) => {
    if (template.value?.[element.key]) {
      locations[element.key] = normalizeLocation(element, template.value[element.key])
    } else {
      delete locations[element.key]
    }
  })
}

const fetchTemplate = async () => {
  loading.value = true
  error.value = ''

  try {
    const { data } = await getAdminTicketTemplate(route.params.id)
    template.value = data.ticket_plantilla

    try {
      await loadTemplateImage(template.value.id)
    } catch {
      error.value = 'No se pudo cargar la imagen de la plantilla. Intente nuevamente.'
    }

    loadLocations()
    await nextTick()
    setScale()
  } catch {
    template.value = null
    revokeTemplateImageUrl()
    error.value = 'No se pudo cargar la plantilla. Intente nuevamente.'
  } finally {
    loading.value = false
  }
}

const getElementStyle = (element) => {
  const location = element.location

  return {
    left: `${location.x * scale.value}px`,
    top: `${location.y * scale.value}px`,
    width: `${location.width * scale.value}px`,
    height: `${location.height * scale.value}px`,
    color: location.color,
    fontSize: `${location.font_size * scale.value}px`,
    fontWeight: location.font_weight,
    textAlign: location.align,
  }
}

const addElement = (key, x = null, y = null) => {
  const element = elementDefinitions.find((item) => item.key === key)

  if (!element) {
    return
  }

  if (!locations[key]) {
    const index = visibleElements.value.length

    locations[key] = normalizeLocation(element, {
      x: x ?? 40,
      y: y ?? 40 + index * 42,
    })
  }

  selectedKey.value = key
}

const handlePaletteDragStart = (event, key) => {
  event.dataTransfer.effectAllowed = 'copy'
  event.dataTransfer.setData('text/plain', key)
}

const handleDrop = (event) => {
  const key = event.dataTransfer.getData('text/plain')
  const rect = canvasRef.value.getBoundingClientRect()
  const x = Math.round((event.clientX - rect.left) / scale.value)
  const y = Math.round((event.clientY - rect.top) / scale.value)

  addElement(
    key,
    Math.min(Math.max(x, 0), templateWidth - 20),
    Math.min(Math.max(y, 0), templateHeight - 20),
  )
}

const startDrag = (event, element) => {
  if (event.target.closest('.ticket-template-editor__resize')) {
    return
  }

  const rect = canvasRef.value.getBoundingClientRect()
  const pointerX = (event.clientX - rect.left) / scale.value
  const pointerY = (event.clientY - rect.top) / scale.value

  selectedKey.value = element.key
  dragState.value = {
    key: element.key,
    offsetX: pointerX - element.location.x,
    offsetY: pointerY - element.location.y,
  }
}

const startResize = (event, element) => {
  event.stopPropagation()
  selectedKey.value = element.key
  resizeState.value = {
    key: element.key,
    startX: event.clientX,
    startY: event.clientY,
    width: element.location.width,
    height: element.location.height,
  }
}

const handlePointerMove = (event) => {
  if (dragState.value && canvasRef.value) {
    const location = locations[dragState.value.key]
    const rect = canvasRef.value.getBoundingClientRect()
    const pointerX = (event.clientX - rect.left) / scale.value
    const pointerY = (event.clientY - rect.top) / scale.value

    location.x = Math.round(
      Math.min(Math.max(pointerX - dragState.value.offsetX, 0), templateWidth - location.width),
    )
    location.y = Math.round(
      Math.min(Math.max(pointerY - dragState.value.offsetY, 0), templateHeight - location.height),
    )
  }

  if (resizeState.value) {
    const location = locations[resizeState.value.key]
    const deltaX = (event.clientX - resizeState.value.startX) / scale.value
    const deltaY = (event.clientY - resizeState.value.startY) / scale.value

    location.width = Math.round(Math.max(24, resizeState.value.width + deltaX))
    location.height = Math.round(Math.max(24, resizeState.value.height + deltaY))
  }
}

const handlePointerUp = () => {
  dragState.value = null
  resizeState.value = null
}

const removeSelectedElement = () => {
  if (!selectedKey.value) {
    return
  }

  delete locations[selectedKey.value]
  selectedKey.value = ''
}

const buildLocationsPayload = () => {
  const payload = {}

  elementDefinitions.forEach((element) => {
    payload[element.key] = locations[element.key]
      ? { ...locations[element.key] }
      : null
  })

  return payload
}

const saveTemplate = async () => {
  if (!template.value?.id) {
    return
  }

  saving.value = true

  try {
    const { data } = await updateAdminTicketTemplate(template.value.id, {
      nombre: template.value.nombre,
      es_predeterminada: template.value.es_predeterminada,
      locations: buildLocationsPayload(),
    })

    template.value = data.ticket_plantilla
    loadLocations()
    notify.success(data.message || 'Plantilla guardada correctamente.')
  } finally {
    saving.value = false
  }
}

const goBack = () => {
  router.push({ name: 'admin-ticket-templates' })
}

onMounted(() => {
  fetchTemplate()
  window.addEventListener('pointermove', handlePointerMove)
  window.addEventListener('pointerup', handlePointerUp)

  if ('ResizeObserver' in window) {
    resizeObserver = new ResizeObserver(setScale)

    nextTick(() => {
      if (canvasRef.value) {
        resizeObserver.observe(canvasRef.value)
      }
    })
  } else {
    window.addEventListener('resize', setScale)
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('pointermove', handlePointerMove)
  window.removeEventListener('pointerup', handlePointerUp)
  window.removeEventListener('resize', setScale)
  resizeObserver?.disconnect()
  revokeTemplateImageUrl()
})
</script>

<template>
  <v-container class="ticket-template-editor-view" fluid>
    <v-btn
      class="mb-6 px-8"
      prepend-icon="mdi-arrow-left"
      rounded="lg"
      variant="outlined"
      @click="goBack"
    >
      Volver
    </v-btn>

    <PageTitle title="Edicion de plantilla" />

    <p class="text-primary font-weight-bold mt-6">
      Para editar la plantilla seleccione el objeto y arrastrelo hasta la posicion donde desea colocarlo.
    </p>

    <v-progress-linear
      v-if="loading"
      class="mt-6"
      color="primary"
      indeterminate
    />

    <v-alert
      v-if="error"
      class="mt-6"
      color="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <template v-if="template">
      <v-row class="mt-6" align="start">
        <v-col cols="12" md="4">
          <div class="d-flex flex-column ga-2">
            <v-btn
              v-for="element in elementDefinitions"
              :key="element.key"
              block
              class="ticket-template-editor__palette-item"
              :color="selectedKey === element.key ? 'primary' : 'secondary'"
              draggable="true"
              rounded="xl"
              :variant="selectedKey === element.key ? 'flat' : 'outlined'"
              @click="addElement(element.key)"
              @dragstart="handlePaletteDragStart($event, element.key)"
            >
              {{ element.label }}
            </v-btn>
          </div>

          <v-card
            v-if="selectedLocation"
            class="mt-6"
            rounded="lg"
            variant="outlined"
          >
            <v-card-title class="text-primary font-weight-bold">
              {{ selectedElement?.label }}
            </v-card-title>

            <v-card-text class="d-flex flex-column ga-3">
              <v-text-field
                v-model.number="selectedLocation.width"
                density="comfortable"
                label="Ancho"
                min="24"
                type="number"
                variant="outlined"
              />

              <v-text-field
                v-model.number="selectedLocation.height"
                density="comfortable"
                label="Alto"
                min="24"
                type="number"
                variant="outlined"
              />

              <template v-if="selectedElement?.type !== 'qr'">
                <v-text-field
                  v-model.number="selectedLocation.font_size"
                  density="comfortable"
                  label="Tamaño de texto"
                  min="8"
                  type="number"
                  variant="outlined"
                />

                <v-text-field
                  v-model="selectedLocation.color"
                  density="comfortable"
                  label="Color"
                  type="color"
                  variant="outlined"
                />

                <v-select
                  v-model="selectedLocation.align"
                  density="comfortable"
                  :items="['left', 'center', 'right']"
                  label="Alineacion"
                  variant="outlined"
                />
              </template>

              <v-btn
                color="error"
                prepend-icon="mdi-delete-outline"
                rounded="lg"
                variant="outlined"
                @click="removeSelectedElement"
              >
                Quitar elemento
              </v-btn>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="8">
          <div class="d-flex justify-end mb-4">
            <v-btn
              color="primary"
              :loading="saving"
              rounded="lg"
              size="large"
              @click="saveTemplate"
            >
              Guardar
            </v-btn>
          </div>

          <div class="ticket-template-editor__stage">
            <div
              ref="canvasRef"
              class="ticket-template-editor__canvas"
              :style="{ aspectRatio: `${templateWidth} / ${templateHeight}` }"
              @dragover.prevent
              @drop.prevent="handleDrop"
            >
              <v-img
                alt="Plantilla de ticket"
                cover
                height="100%"
                :src="templateImageUrl || template.image_url"
                width="100%"
              />

              <div class="ticket-template-editor__overlay">
                <div
                  v-for="element in visibleElements"
                  :key="element.key"
                  class="ticket-template-editor__element"
                  :class="{
                    'ticket-template-editor__element--selected': selectedKey === element.key,
                    'ticket-template-editor__element--qr': element.type === 'qr',
                  }"
                  :style="getElementStyle(element)"
                  @pointerdown.prevent="startDrag($event, element)"
                >
                  <v-icon
                    v-if="element.type === 'qr'"
                    icon="mdi-qrcode"
                    size="100%"
                  />

                  <span v-else>{{ element.sample }}</span>

                  <span
                    class="ticket-template-editor__resize"
                    @pointerdown.prevent="startResize($event, element)"
                  />
                </div>
              </div>
            </div>
          </div>
        </v-col>
      </v-row>
    </template>
  </v-container>
</template>

<style scoped>
.ticket-template-editor-view {
  color: rgb(var(--v-theme-primary));
}

.ticket-template-editor__palette-item {
  justify-content: center;
  min-height: 34px;
  text-transform: none;
}

.ticket-template-editor__stage {
  overflow-x: auto;
  padding-bottom: 8px;
}

.ticket-template-editor__canvas {
  border: 1px solid rgb(var(--v-theme-primary));
  min-width: 320px;
  position: relative;
  width: min(100%, 760px);
}

.ticket-template-editor__overlay {
  inset: 0;
  position: absolute;
}

.ticket-template-editor__element {
  align-items: center;
  border: 1px dashed rgba(var(--v-theme-primary), 0.52);
  cursor: move;
  display: flex;
  justify-content: center;
  overflow: hidden;
  position: absolute;
  touch-action: none;
  user-select: none;
}

.ticket-template-editor__element--selected {
  border-color: rgb(var(--v-theme-blueLigth));
  border-style: solid;
  box-shadow: 0 0 0 2px rgba(var(--v-theme-blueLigth), 0.16);
}

.ticket-template-editor__element--qr {
  color: rgb(var(--v-theme-primary));
}

.ticket-template-editor__resize {
  background: rgb(var(--v-theme-blueLigth));
  border-radius: 50%;
  bottom: -5px;
  cursor: nwse-resize;
  height: 12px;
  position: absolute;
  right: -5px;
  width: 12px;
}
</style>
