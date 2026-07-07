<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import BaseModal from '@/components/common/BaseModal.vue'
import { getAdminTicketTemplateImageObjectUrl } from '@/services/adminTicketTemplateService'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  template: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'close'])

const templateWidth = Number(import.meta.env.VITE_TICKET_TEMPLATE_WIDTH ?? 1000)
const templateHeight = Number(import.meta.env.VITE_TICKET_TEMPLATE_HEIGHT ?? 500)
const previewImageUrl = ref('')
const imageLoading = ref(false)
const imageError = ref('')

const dialogModel = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  },
})

const revokePreviewImageUrl = () => {
  if (previewImageUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(previewImageUrl.value)
  }

  previewImageUrl.value = ''
}

const loadPreviewImage = async () => {
  if (!props.modelValue || !props.template?.id) {
    revokePreviewImageUrl()
    return
  }

  imageLoading.value = true
  imageError.value = ''

  try {
    revokePreviewImageUrl()
    previewImageUrl.value = await getAdminTicketTemplateImageObjectUrl(props.template.id)
  } catch {
    imageError.value = 'No se pudo cargar la imagen de la plantilla.'
  } finally {
    imageLoading.value = false
  }
}

const elements = computed(() => [
  { key: 'operador_location', label: 'Operador de transporte' },
  { key: 'codigo_ticket_location', label: 'Numero de ticket' },
  { key: 'ruta_location', label: 'Ruta' },
  { key: 'asiento_location', label: 'Asiento' },
  { key: 'salida_location', label: 'Salida' },
  { key: 'fecha_hora_location', label: 'Fecha y hora' },
  { key: 'precio_location', label: 'Precio' },
  { key: 'qr_location', label: 'QR', isQr: true },
].map((element) => ({
  ...element,
  location: props.template?.[element.key],
})).filter((element) => element.location))

const getElementStyle = (element) => {
  const location = element.location

  return {
    left: `${location.x ?? 0}px`,
    top: `${location.y ?? 0}px`,
    width: `${location.width ?? (element.isQr ? 100 : 180)}px`,
    height: `${location.height ?? (element.isQr ? 100 : 32)}px`,
    color: location.color ?? '#001233',
    fontSize: `${location.font_size ?? 18}px`,
    fontWeight: location.font_weight ?? 800,
    textAlign: location.align ?? 'center',
  }
}

watch(
  () => [props.modelValue, props.template?.id],
  loadPreviewImage,
  { immediate: true },
)

onBeforeUnmount(() => {
  revokePreviewImageUrl()
})
</script>

<template>
  <BaseModal
    v-model="dialogModel"
    :show-accept-button="false"
    cancel-text="Cerrar"
    max-width="920"
    title="Previsualizar plantilla"
    @cancel="$emit('close')"
    @close="$emit('close')"
  >
    <v-progress-linear
      v-if="imageLoading"
      class="mb-4"
      color="primary"
      indeterminate
    />

    <v-alert
      v-if="imageError"
      class="mb-4"
      color="error"
      type="error"
      variant="tonal"
    >
      {{ imageError }}
    </v-alert>

    <div
      v-if="template"
      class="ticket-preview-stage mx-auto"
    >
      <div
        class="ticket-preview-canvas"
        :style="{
          aspectRatio: `${templateWidth} / ${templateHeight}`,
          width: `${templateWidth}px`,
        }"
      >
        <v-img
          alt="Plantilla de ticket"
          cover
          height="100%"
          :src="previewImageUrl || template.image_url"
          width="100%"
        />

        <div class="ticket-preview-overlay">
          <div
            v-for="element in elements"
            :key="element.key"
            class="ticket-preview-element"
            :class="{ 'ticket-preview-element--qr': element.isQr }"
            :style="getElementStyle(element)"
          >
            <v-icon
              v-if="element.isQr"
              icon="mdi-qrcode"
              size="100%"
            />

            <span v-else>{{ element.label }}</span>
          </div>
        </div>
      </div>
    </div>
  </BaseModal>
</template>

<style scoped>
.ticket-preview-stage {
  max-width: 100%;
  overflow-x: auto;
}

.ticket-preview-canvas {
  border: 1px solid rgb(var(--v-theme-primary));
  max-width: none;
  position: relative;
}

.ticket-preview-overlay {
  inset: 0;
  pointer-events: none;
  position: absolute;
}

.ticket-preview-element {
  align-items: center;
  display: flex;
  justify-content: center;
  overflow: hidden;
  position: absolute;
  transform-origin: top left;
}

.ticket-preview-element--qr {
  color: rgb(var(--v-theme-primary));
}
</style>
