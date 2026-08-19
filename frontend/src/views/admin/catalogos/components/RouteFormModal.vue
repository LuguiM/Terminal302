<script setup>
import { computed, reactive, watch } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { helpers, minValue, numeric, required } from '@vuelidate/validators'

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
  route: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'submit', 'cancel'])

const form = reactive({
  id: null,
  ruta: '',
  denominacion: '',
  tarifa: '',
})

const isEdit = computed(() => props.mode === 'edit')
const title = computed(() => (isEdit.value ? 'Editar ruta' : 'Registrar ruta'))
const acceptText = computed(() => (isEdit.value ? 'Editar' : 'Registrar'))

const maxLength = (limit) => helpers.withParams(
  { type: 'maxLength', max: limit },
  (value) => !helpers.req(value) || value.toString().length <= limit,
)

const routeCode = helpers.regex(/^\d+(?:-?[A-Z0-9])?$/)

const toTitleCase = (value) => value
  .trim()
  .toLocaleLowerCase('es-SV')
  .replace(/(^|[\s-])\p{L}/gu, (letter) => letter.toLocaleUpperCase('es-SV'))

const rules = computed(() => ({
  ruta: {
    required: helpers.withMessage('El codigo de ruta es requerido.', required),
    maxLength: helpers.withMessage('El codigo de ruta no debe superar 50 caracteres.', maxLength(50)),
    routeCode: helpers.withMessage(
      'Use numeros y un sufijo opcional, con guion o sin el (ejemplo: 302-B).',
      routeCode,
    ),
  },
  denominacion: {
    required: helpers.withMessage('La denominacion es requerida.', required),
    maxLength: helpers.withMessage('La denominacion no debe superar 255 caracteres.', maxLength(255)),
  },
  tarifa: {
    required: helpers.withMessage('La tarifa es requerida.', required),
    numeric: helpers.withMessage('La tarifa debe ser numerica.', numeric),
    minValue: helpers.withMessage('La tarifa debe ser mayor o igual a 0.', minValue(0)),
  },
}))

const v$ = useVuelidate(rules, form, {
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

const resetForm = () => {
  form.id = null
  form.ruta = ''
  form.denominacion = ''
  form.tarifa = ''
  v$.value.$reset()
}

const fillForm = () => {
  form.id = props.route?.id ?? null
  form.ruta = props.route?.ruta ?? ''
  form.denominacion = props.route?.denominacion ?? ''
  form.tarifa = props.route?.tarifa ?? ''
  v$.value.$reset()
}

const submit = async () => {
  const isValid = await v$.value.$validate()

  if (!isValid) {
    return
  }

  emit('submit', {
    id: form.id,
    ruta: form.ruta.trim().toLocaleUpperCase('es-SV'),
    denominacion: toTitleCase(form.denominacion),
    tarifa: Number(form.tarifa),
  })
}

watch(
  () => [props.modelValue, props.route, props.mode],
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
    max-width="600"
    :title="title"
    @accept="submit"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
  >
    <div class="d-flex flex-column ga-5">
      <div>
        <div class="text-primary font-weight-bold mb-1">
          Codigo de ruta*
        </div>
        <v-text-field
          :model-value="form.ruta"
          density="comfortable"
          :error-messages="v$.ruta.$errors.map((error) => error.$message)"
          placeholder="Ej: 302-B"
          variant="outlined"
          @blur="v$.ruta.$touch"
          @update:model-value="form.ruta = String($event ?? '').toLocaleUpperCase('es-SV')"
        />
      </div>

      <div>
        <div class="text-primary font-weight-bold mb-1">
          Denominacion*
        </div>
        <v-text-field
          v-model="form.denominacion"
          density="comfortable"
          :error-messages="v$.denominacion.$errors.map((error) => error.$message)"
          placeholder="Ej: Centro - Periferico"
          variant="outlined"
          @blur="v$.denominacion.$touch"
        />
      </div>

      <div>
        <div class="text-primary font-weight-bold mb-1">
          Tarifa*
        </div>
        <v-text-field
          v-model="form.tarifa"
          density="comfortable"
          :error-messages="v$.tarifa.$errors.map((error) => error.$message)"
          placeholder="Ej: 2.50"
          type="number"
          variant="outlined"
          @blur="v$.tarifa.$touch"
        />
      </div>
    </div>
  </BaseModal>
</template>
