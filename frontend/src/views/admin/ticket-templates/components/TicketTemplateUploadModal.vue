<script setup>
import { computed, ref, watch } from 'vue'

import BaseModal from '@/components/common/BaseModal.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'cancel', 'submit'])

const fileInput = ref(null)
const selectedFile = ref(null)
const error = ref('')
const isDragging = ref(false)

const templateWidth = Number(import.meta.env.VITE_TICKET_TEMPLATE_WIDTH ?? 1000)
const templateHeight = Number(import.meta.env.VITE_TICKET_TEMPLATE_HEIGHT ?? 500)
const maxSizeMb = Number(import.meta.env.VITE_TICKET_TEMPLATE_MAX_SIZE_MB ?? 10)
const maxSizeBytes = maxSizeMb * 1024 * 1024

const dialogModel = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  },
})

const fileName = computed(() => selectedFile.value?.name ?? '')

const reset = () => {
  selectedFile.value = null
  error.value = ''
  isDragging.value = false

  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const getImageDimensions = (file) => new Promise((resolve, reject) => {
  const image = new Image()
  const url = URL.createObjectURL(file)

  image.onload = () => {
    URL.revokeObjectURL(url)
    resolve({
      width: image.naturalWidth,
      height: image.naturalHeight,
    })
  }

  image.onerror = () => {
    URL.revokeObjectURL(url)
    reject(new Error('No se pudo leer la imagen.'))
  }

  image.src = url
})

const validateFile = async (file) => {
  if (!file) {
    return false
  }

  const allowedTypes = ['image/png', 'image/jpeg']

  if (!allowedTypes.includes(file.type)) {
    error.value = 'Seleccione una imagen PNG, JPG o JPEG.'
    return false
  }

  if (file.size > maxSizeBytes) {
    error.value = `La imagen no debe superar ${maxSizeMb} MB.`
    return false
  }

  const dimensions = await getImageDimensions(file)

  if (dimensions.width !== templateWidth || dimensions.height !== templateHeight) {
    error.value = `La imagen debe medir ${templateWidth}x${templateHeight} px.`
    return false
  }

  error.value = ''
  return true
}

const selectFile = async (file) => {
  try {
    if (await validateFile(file)) {
      selectedFile.value = file
    } else {
      selectedFile.value = null
    }
  } catch {
    selectedFile.value = null
    error.value = 'No se pudo validar la imagen seleccionada.'
  }
}

const handleInputChange = (event) => {
  selectFile(event.target.files?.[0])
}

const handleDrop = (event) => {
  isDragging.value = false
  selectFile(event.dataTransfer.files?.[0])
}

const handleSubmit = () => {
  if (!selectedFile.value) {
    error.value = 'Seleccione una imagen para continuar.'
    return
  }

  emit('submit', selectedFile.value)
}

const handleCancel = () => {
  reset()
  emit('cancel')
}

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) {
      reset()
    }
  },
)
</script>

<template>
  <BaseModal
    v-model="dialogModel"
    accept-text="Subir"
    cancel-text="Cancelar"
    :disabled="!selectedFile"
    :loading="loading"
    max-width="760"
    title="Subir Plantilla de Ticket"
    @accept="handleSubmit"
    @cancel="handleCancel"
    @close="handleCancel"
  >
    <div
      class="ticket-template-dropzone d-flex flex-column align-center justify-center text-center pa-8"
      :class="{ 'ticket-template-dropzone--active': isDragging }"
      role="button"
      tabindex="0"
      @click="fileInput?.click()"
      @dragenter.prevent="isDragging = true"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="handleDrop"
      @keyup.enter="fileInput?.click()"
      @keyup.space="fileInput?.click()"
    >
      <input
        ref="fileInput"
        accept=".png,.jpg,.jpeg,image/png,image/jpeg"
        class="d-none"
        type="file"
        @change="handleInputChange"
      >

      <v-icon
        color="primary"
        icon="mdi-upload"
        size="52"
      />

      <p class="text-primary font-weight-bold mt-8 mb-2">
        Haz clic para seleccionar un archivo
      </p>

      <p class="text-secondary font-weight-bold mb-6">
        o arrastra y suelta aqui
      </p>

      <p class="text-primary font-weight-bold mb-0">
        Formatos soportados: PNG, JPG, JPEG (maximo {{ maxSizeMb }} MB)
      </p>

      <p class="text-secondary text-caption mt-2 mb-0">
        Medidas requeridas: {{ templateWidth }}x{{ templateHeight }} px
      </p>
    </div>

    <v-alert
      v-if="fileName"
      class="mt-4"
      color="success"
      variant="tonal"
    >
      Archivo seleccionado: {{ fileName }}
    </v-alert>

    <v-alert
      v-if="error"
      class="mt-4"
      color="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>
  </BaseModal>
</template>

<style scoped>
.ticket-template-dropzone {
  border: 2px dashed rgb(var(--v-theme-blueLigth));
  border-radius: 8px;
  cursor: pointer;
  min-height: 310px;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

.ticket-template-dropzone--active {
  background-color: rgba(var(--v-theme-blueLigth), 0.08);
}
</style>
