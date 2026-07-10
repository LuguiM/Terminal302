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
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'submit', 'cancel'])

const form = reactive({
  ruta: '',
  denominacion: '',
  tarifa: '',
})

const maxLength = (limit) => helpers.withParams(
  { type: 'maxLength', max: limit },
  (value) => !helpers.req(value) || value.toString().length <= limit,
)

const rules = computed(() => ({
  ruta: {
    required: helpers.withMessage('El codigo de ruta es requerido.', required),
    maxLength: helpers.withMessage('El codigo de ruta no debe superar 50 caracteres.', maxLength(50)),
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
  form.ruta = ''
  form.denominacion = ''
  form.tarifa = ''
  v$.value.$reset()
}

const submit = async () => {
  const isValid = await v$.value.$validate()

  if (!isValid) {
    return
  }

  emit('submit', {
    ruta: form.ruta.trim(),
    denominacion: form.denominacion.trim(),
    tarifa: Number(form.tarifa),
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
    <div class="d-flex flex-column ga-5">
      <div>
        <div class="text-primary font-weight-bold mb-1">
          Codigo de ruta*
        </div>
        <v-text-field
          v-model="form.ruta"
          density="comfortable"
          :error-messages="v$.ruta.$errors.map((error) => error.$message)"
          placeholder="Ej: R-001"
          variant="outlined"
          @blur="v$.ruta.$touch"
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
