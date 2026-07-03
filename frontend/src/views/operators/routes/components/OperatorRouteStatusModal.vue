<script setup>
import { computed } from 'vue'

import BaseModal from '@/components/common/BaseModal.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  route: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  isActive: {
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

const actionText = computed(() => (props.isActive ? 'Desactivar' : 'Activar'))
const targetStatusText = computed(() => (props.isActive ? 'desactivado' : 'activado'))
</script>

<template>
  <BaseModal
    v-model="dialogModel"
    :accept-text="actionText"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="600"
    :title="`${actionText} ruta`"
    @accept="$emit('confirm', route)"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
  >
    <div class="text-center text-secondary font-weight-bold d-flex flex-column ga-5">
      <p>Esta accion cambiara el estado de la ruta a {{ targetStatusText }}</p>
      <p>&iquest;Desea {{ actionText.toLowerCase() }} la ruta?</p>
    </div>
  </BaseModal>
</template>
