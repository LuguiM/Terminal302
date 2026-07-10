<script setup>
import { reactive, watch } from 'vue'

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

const emit = defineEmits(['update:modelValue', 'submit'])

const form = reactive({
  motivo_cierre: '',
})

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) {
      form.motivo_cierre = ''
    }
  },
)

const handleSubmit = () => {
  emit('submit', {
    motivo_cierre: form.motivo_cierre || null,
  })
}
</script>

<template>
  <BaseModal
    :loading="loading"
    :model-value="modelValue"
    title="Cerrar venta"
    accept-text="Cerrar venta"
    @accept="handleSubmit"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="text-center text-secondary font-weight-bold mb-5">
      Esta accion cerrara la venta del horario seleccionado.
    </div>

    <v-textarea
      v-model="form.motivo_cierre"
      auto-grow
      density="comfortable"
      label="Motivo (opcional)"
      rows="3"
      variant="outlined"
    />
  </BaseModal>
</template>
