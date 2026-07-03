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
    accept-text="Restablecer"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="640"
    title="Restablecer contraseña"
    @accept="$emit('confirm', user)"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
  >
    <div class="text-center text-secondary font-weight-bold d-flex flex-column ga-5">
      <p>
        Se enviara un correo al usuario <strong>{{ userName }}</strong> para que realice el cambio de contraseña
      </p>
      <p>
        ¿Esta seguro de restablecer la contraseña al usuario seleccionado?
      </p>
    </div>
  </BaseModal>
</template>
