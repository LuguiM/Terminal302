<script setup>
import { computed, ref, watch } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { helpers, required } from '@vuelidate/validators'

import BaseModal from '@/components/common/BaseModal.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  routesLoading: {
    type: Boolean,
    default: false,
  },
  routes: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'submit', 'cancel'])

const selectedRouteId = ref(null)

const rules = computed(() => ({
  selectedRouteId: {
    required: helpers.withMessage('Debe seleccionar una ruta.', required),
  },
}))

const v$ = useVuelidate(rules, { selectedRouteId }, {
  $autoDirty: true,
})

const dialogModel = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  },
})

const routeOptions = computed(() =>
  props.routes.map((route) => ({
    ...route,
    label: `${route.ruta} - ${route.denominacion}`,
  })),
)

const resetForm = () => {
  selectedRouteId.value = null
  v$.value.$reset()
}

const submit = async () => {
  const isValid = await v$.value.$validate()

  if (!isValid) {
    return
  }

  emit('submit', {
    ruta_id: selectedRouteId.value,
  })
}

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      resetForm()
    }
  },
)
</script>

<template>
  <BaseModal
    v-model="dialogModel"
    accept-text="Registrar"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="600"
    title="Registrar ruta"
    @accept="submit"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
  >
    <div>
      <div class="text-primary font-weight-bold mb-1">
        Codigo de ruta*
      </div>
      <v-autocomplete
        v-model="selectedRouteId"
        clearable
        density="comfortable"
        :error-messages="v$.selectedRouteId.$errors.map((error) => error.$message)"
        item-title="label"
        item-value="id"
        :items="routeOptions"
        :loading="routesLoading"
        no-data-text="No hay rutas disponibles."
        placeholder="Seleccione una ruta"
        variant="outlined"
        @blur="v$.selectedRouteId.$touch"
      />
    </div>
  </BaseModal>
</template>
