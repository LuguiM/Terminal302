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
    accept-color="primary"
    accept-text="Eliminar"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="620"
    title="Eliminar plantilla"
    @accept="$emit('confirm')"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="text-center text-secondary font-weight-bold">
      <p>Esta accion eliminara la plantilla seleccionada</p>
      <p class="mb-0">¿Desea continuar con la eliminacion de la plantilla?</p>
    </div>
  </BaseModal>
</template>
