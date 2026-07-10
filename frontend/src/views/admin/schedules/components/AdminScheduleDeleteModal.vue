<script setup>
import { computed } from 'vue'

import BaseModal from '@/components/common/BaseModal.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  schedule: {
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
    accept-color="primary"
    accept-text="Eliminar"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="620"
    title="Eliminar horario"
    @accept="$emit('confirm', schedule)"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
  >
    <div class="text-center text-secondary font-weight-bold d-flex flex-column ga-6">
      <p>Esta accion eliminara el horario seleccionado</p>
      <p>&iquest;Desea continuar con la eliminacion del horario?</p>
    </div>
  </BaseModal>
</template>
