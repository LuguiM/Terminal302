<script setup>
import { computed } from 'vue'

import BaseModal from '@/components/common/BaseModal.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  details: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue'])

const dialogModel = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  },
})

const errors = computed(() => {
  const backendErrors = props.details?.errors

  if (!backendErrors || typeof backendErrors !== 'object') {
    return []
  }

  return Object.entries(backendErrors).flatMap(([field, messages]) => {
    const normalizedMessages = Array.isArray(messages) ? messages : [messages]

    return normalizedMessages.map((message) => ({
      field,
      message,
    }))
  })
})

const attemptedAt = computed(() => {
  if (!props.details?.attempted_at) {
    return 'No disponible'
  }

  return new Intl.DateTimeFormat('es-SV', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(props.details.attempted_at))
})
</script>

<template>
  <BaseModal
    v-model="dialogModel"
    accept-text="Cerrar"
    :show-cancel-button="false"
    title="Detalle de validacion"
    @accept="dialogModel = false"
  >
    <v-list
      v-if="details"
      class="bg-transparent"
      density="comfortable"
    >
      <v-list-item>
        <v-list-item-title class="font-weight-bold">
          Codigo leido
        </v-list-item-title>
        <v-list-item-subtitle class="text-wrap">
          {{ details.codigo_ticket || 'No disponible' }}
        </v-list-item-subtitle>
      </v-list-item>

      <v-list-item>
        <v-list-item-title class="font-weight-bold">
          Mensaje
        </v-list-item-title>
        <v-list-item-subtitle class="text-wrap">
          {{ details.message || 'No se pudo validar el ticket.' }}
        </v-list-item-subtitle>
      </v-list-item>

      <v-list-item>
        <v-list-item-title class="font-weight-bold">
          Fecha y hora
        </v-list-item-title>
        <v-list-item-subtitle>
          {{ attemptedAt }}
        </v-list-item-subtitle>
      </v-list-item>

      <v-list-item v-if="details.status">
        <v-list-item-title class="font-weight-bold">
          Codigo HTTP
        </v-list-item-title>
        <v-list-item-subtitle>
          {{ details.status }}
        </v-list-item-subtitle>
      </v-list-item>
    </v-list>

    <v-alert
      v-if="errors.length"
      class="mt-2"
      color="error"
      variant="tonal"
    >
      <div class="font-weight-bold mb-2">
        Errores de validacion
      </div>
      <div
        v-for="error in errors"
        :key="`${error.field}-${error.message}`"
        class="text-body-2"
      >
        {{ error.field }}: {{ error.message }}
      </div>
    </v-alert>
  </BaseModal>
</template>
