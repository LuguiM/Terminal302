<script setup>
import BaseModal from '@/components/common/BaseModal.vue'

defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  summary: {
    type: Object,
    default: null,
  },
})

defineEmits([
  'update:modelValue',
  'same-schedule',
  'schedules',
  'routes',
  'deliveries',
])
</script>

<template>
  <BaseModal
    :model-value="modelValue"
    title="Venta generada"
    :show-accept-button="false"
    :show-cancel-button="false"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="text-center text-secondary font-weight-bold">
      <p class="mb-2">
        Los tickets fueron generados correctamente.
      </p>
      <p
        v-if="summary"
        class="mb-0"
      >
        Cantidad generada: {{ summary.cantidad_generada ?? '-' }}
      </p>
    </div>

    <template #actions>
      <div class="d-flex flex-column flex-sm-row flex-wrap justify-center ga-3 w-100">
        <v-btn
          color="primary"
          rounded="lg"
          variant="flat"
          @click="$emit('same-schedule')"
        >
          Seguir vendiendo
        </v-btn>

        <v-btn
          rounded="lg"
          variant="outlined"
          @click="$emit('schedules')"
        >
          Seleccionar horario
        </v-btn>

        <v-btn
          rounded="lg"
          variant="outlined"
          @click="$emit('routes')"
        >
          Seleccionar ruta
        </v-btn>

        <v-btn
          color="secondary"
          rounded="lg"
          variant="tonal"
          @click="$emit('deliveries')"
        >
          Entregas digitales
        </v-btn>
      </div>
    </template>
  </BaseModal>
</template>
