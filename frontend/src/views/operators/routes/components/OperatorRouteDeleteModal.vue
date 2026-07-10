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
    accept-text="Eliminar"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="600"
    title="Eliminar ruta"
    @accept="$emit('confirm', route)"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
  >
    <div class="text-center text-secondary font-weight-bold d-flex flex-column ga-5">
      <p>
        Esta accion eliminara la ruta asignada al operador. Asegurese de que no este asociada a unidades de transporte antes de proceder.
      </p>
      <p>&iquest;Desea continuar con la eliminacion de la ruta?</p>
    </div>
  </BaseModal>
</template>
