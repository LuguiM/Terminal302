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

const userName = computed(() => props.user?.name ?? 'usuario seleccionado')
</script>

<template>
  <BaseModal
    v-model="dialogModel"
    accept-text="Eliminar"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="600"
    title="Eliminar usuario"
    @accept="$emit('confirm', user)"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
  >
    <div class="text-center text-secondary font-weight-bold d-flex flex-column ga-5">
      <p>
        Esta acción eliminara al usuario <strong>{{ userName }}</strong> del sistema quitandole el acceso de forma permanente, esta acción es irreversible
      </p>
      <p>¿Desea continuar con la eliminación del usuario?</p>
    </div>
  </BaseModal>
</template>
