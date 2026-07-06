<script setup>
import { computed, reactive, watch } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { helpers, integer, minValue, required } from '@vuelidate/validators'

import BaseModal from '@/components/common/BaseModal.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  mode: {
    type: String,
    default: 'create',
    validator: (value) => ['create', 'edit'].includes(value),
  },
  bus: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  catalogsLoading: {
    type: Boolean,
    default: false,
  },
  routes: {
    type: Array,
    default: () => [],
  },
  busTypes: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue', 'submit', 'cancel'])

const form = reactive({
  id: null,
  marca: '',
  nombre_unidad: '',
  tipo_bus_id: null,
  capacidad: '',
  placa: '',
  ruta_id: null,
})

const isEdit = computed(() => props.mode === 'edit')

const title = computed(() =>
  isEdit.value
    ? 'Editar unidad de transporte'
    : 'Registrar unidad de transporte',
)

const acceptText = computed(() => (isEdit.value ? 'Editar' : 'Registrar'))

const dialogModel = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  },
})

const selectedBusType = computed(() =>
  props.busTypes.find((busType) => Number(busType.id) === Number(form.tipo_bus_id)),
)

const routeOptions = computed(() =>
  props.routes.map((route) => ({
    ...route,
    label: `${route.ruta} - ${route.denominacion}`,
  })),
)

const busTypeOptions = computed(() =>
  props.busTypes.map((busType) => ({
    ...busType,
    label: formatBusTypeName(busType.nombre),
  })),
)

const maxLength = (limit) => helpers.withParams(
  { type: 'maxLength', max: limit },
  (value) => !helpers.req(value) || value.toString().length <= limit,
)

const plateMatchesBusType = (value) => {
  if (!helpers.req(value)) {
    return true
  }

  const expectedPrefix = getExpectedPlatePrefix(selectedBusType.value?.nombre)

  if (!expectedPrefix) {
    return true
  }

  return new RegExp(`^${expectedPrefix}-[A-Z0-9]{3,6}$`).test(
    value.toString().trim().toUpperCase(),
  )
}

const rules = computed(() => ({
  marca: {
    required: helpers.withMessage('La marca es requerida.', required),
    maxLength: helpers.withMessage('La marca no debe superar 100 caracteres.', maxLength(100)),
  },
  nombre_unidad: {
    maxLength: helpers.withMessage('El nombre de la unidad no debe superar 100 caracteres.', maxLength(100)),
  },
  tipo_bus_id: {
    required: helpers.withMessage('El tipo de servicio es requerido.', required),
  },
  capacidad: {
    required: helpers.withMessage('La capacidad es requerida.', required),
    integer: helpers.withMessage('La capacidad debe ser un numero entero.', integer),
    minValue: helpers.withMessage('La capacidad debe ser mayor o igual a 1.', minValue(1)),
  },
  placa: {
    required: helpers.withMessage('La placa es requerida.', required),
    maxLength: helpers.withMessage('La placa no debe superar 50 caracteres.', maxLength(50)),
    plateMatchesBusType: helpers.withMessage(
      () => getPlateValidationMessage(),
      plateMatchesBusType,
    ),
  },
  ruta_id: {
    required: helpers.withMessage('La ruta de la unidad es requerida.', required),
  },
}))

const v$ = useVuelidate(rules, form, {
  $autoDirty: true,
})

const fillForm = () => {
  form.id = props.bus?.id ?? null
  form.marca = props.bus?.marca ?? ''
  form.nombre_unidad = props.bus?.nombre_unidad ?? ''
  form.tipo_bus_id = props.bus?.tipo_bus?.id ?? null
  form.capacidad = props.bus?.capacidad ?? ''
  form.placa = props.bus?.placa ?? ''
  form.ruta_id = props.bus?.ruta?.id ?? null
  v$.value.$reset()
}

const resetForm = () => {
  form.id = null
  form.marca = ''
  form.nombre_unidad = ''
  form.tipo_bus_id = null
  form.capacidad = ''
  form.placa = ''
  form.ruta_id = null
  v$.value.$reset()
}

const submit = async () => {
  const isValid = await v$.value.$validate()

  if (!isValid) {
    return
  }

  emit('submit', {
    id: form.id,
    ruta_id: form.ruta_id,
    placa: form.placa.trim().toUpperCase(),
    marca: form.marca.trim(),
    nombre_unidad: form.nombre_unidad.trim() || null,
    capacidad: Number(form.capacidad),
    tipo_bus_id: form.tipo_bus_id,
  })
}

const getExpectedPlatePrefix = (busTypeName = '') => {
  const normalizedName = normalizeBusTypeName(busTypeName)

  if (['bus', 'autobus'].includes(normalizedName)) {
    return 'AB'
  }

  if (['microbus', 'coaster'].includes(normalizedName)) {
    return 'MB'
  }

  return null
}

const getPlateValidationMessage = () => {
  const expectedPrefix = getExpectedPlatePrefix(selectedBusType.value?.nombre)

  if (!expectedPrefix) {
    return 'La placa no coincide con el tipo de servicio seleccionado.'
  }

  return `La placa debe tener formato ${expectedPrefix}- seguido de 3 a 6 caracteres alfanumericos.`
}

const normalizeBusTypeName = (value = '') => {
  return value
    .toString()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
}

const formatBusTypeName = (value = '') => {
  return value
    .toString()
    .replace(/^\w/, (letter) => letter.toUpperCase())
}

watch(
  () => [props.modelValue, props.bus, props.mode],
  ([isOpen]) => {
    if (!isOpen) {
      return
    }

    if (isEdit.value) {
      fillForm()
      return
    }

    resetForm()
  },
  { immediate: true },
)
</script>

<template>
  <BaseModal
    v-model="dialogModel"
    :accept-text="acceptText"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="640"
    :title="title"
    @accept="submit"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
  >
    <div class="d-flex flex-column ga-4">
      <div>
        <div class="text-primary font-weight-bold mb-1">
          Marca*
        </div>
        <v-text-field
          v-model="form.marca"
          density="comfortable"
          :error-messages="v$.marca.$errors.map((error) => error.$message)"
          placeholder="Ej: Mercedes-Benz"
          variant="outlined"
          @blur="v$.marca.$touch"
        />
      </div>

      <div>
        <div class="text-primary font-weight-bold mb-1">
          Nombre de la unidad
        </div>
        <v-text-field
          v-model="form.nombre_unidad"
          density="comfortable"
          :error-messages="v$.nombre_unidad.$errors.map((error) => error.$message)"
          placeholder="Ej: Kenias"
          variant="outlined"
          @blur="v$.nombre_unidad.$touch"
        />
      </div>

      <div>
        <div class="text-primary font-weight-bold mb-1">
          Tipo de unidad*
        </div>
        <v-autocomplete
          v-model="form.tipo_bus_id"
          clearable
          density="comfortable"
          :error-messages="v$.tipo_bus_id.$errors.map((error) => error.$message)"
          item-title="label"
          item-value="id"
          :items="busTypeOptions"
          :loading="catalogsLoading"
          no-data-text="No hay tipos de unidades disponibles."
          placeholder="Ej: Autobus"
          variant="outlined"
          @blur="v$.tipo_bus_id.$touch"
        />
      </div>

      <div>
        <div class="text-primary font-weight-bold mb-1">
          Capacidad*
        </div>
        <v-text-field
          v-model="form.capacidad"
          density="comfortable"
          :error-messages="v$.capacidad.$errors.map((error) => error.$message)"
          placeholder="Ej: 50"
          type="number"
          variant="outlined"
          @blur="v$.capacidad.$touch"
        />
      </div>

      <div>
        <div class="text-primary font-weight-bold mb-1">
          Placa*
        </div>
        <v-text-field
          v-model="form.placa"
          density="comfortable"
          :error-messages="v$.placa.$errors.map((error) => error.$message)"
          placeholder="Ej: AB-123"
          variant="outlined"
          @blur="v$.placa.$touch"
        />
      </div>

      <div>
        <div class="text-primary font-weight-bold mb-1">
          Ruta de la unidad*
        </div>
        <v-autocomplete
          v-model="form.ruta_id"
          clearable
          density="comfortable"
          :error-messages="v$.ruta_id.$errors.map((error) => error.$message)"
          item-title="label"
          item-value="id"
          :items="routeOptions"
          :loading="catalogsLoading"
          no-data-text="No hay rutas activas disponibles."
          placeholder="Seleccione"
          variant="outlined"
          @blur="v$.ruta_id.$touch"
        />
      </div>
    </div>
  </BaseModal>
</template>
