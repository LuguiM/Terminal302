<script setup>
import BaseModal from '@/components/common/BaseModal.vue'

defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  template: {
    type: Object,
    default: null,
  },
})

defineEmits(['update:modelValue', 'cancel', 'confirm'])
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    accept-text="Establecer"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="620"
    title="Establecer predeterminada"
    @accept="$emit('confirm')"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="text-center text-secondary font-weight-bold">
      <p>Esta accion establecera como predeterminada a la plantilla seleccionada</p>
      <p class="mb-0">¿Desea establecer la plantilla como predeterminada?</p>
    </div>
  </BaseModal>
</template>
