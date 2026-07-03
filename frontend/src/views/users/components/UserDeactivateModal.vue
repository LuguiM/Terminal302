<script setup>
import { computed } from 'vue'

import BaseModal from '@/components/common/BaseModal.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  user: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const dialogModel = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  },
})
</script>

<template>
  <BaseModal
    v-model="dialogModel"
    accept-text="Desactivar"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="600"
    title="Desactivar usuario"
    @accept="$emit('confirm', user)"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
  >
    <div class="text-center text-secondary font-weight-bold d-flex flex-column ga-5">
      <p>Esta acción cambiara el estado del usuario a desactivado quitandole el acceso al sistema</p>
      <p>¿Esta seguro de cambiar el estado del usuario?</p>
    </div>
  </BaseModal>
</template>
