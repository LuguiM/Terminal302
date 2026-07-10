<script setup>
import { computed, reactive, watch } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { email, helpers, required } from '@vuelidate/validators'

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
  employee: {
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
  name: '',
  email: '',
})

const isEdit = computed(() => props.mode === 'edit')
const title = computed(() => (isEdit.value ? 'Editar empleado' : 'Nuevo empleado'))
const acceptText = computed(() => (isEdit.value ? 'Editar' : 'Registrar'))

const maxLength = (limit) => helpers.withParams(
  { type: 'maxLength', max: limit },
  (value) => !helpers.req(value) || value.toString().length <= limit,
)

const rules = computed(() => ({
  name: {
    required: helpers.withMessage('El nombre es requerido.', required),
    maxLength: helpers.withMessage('El nombre no debe superar 255 caracteres.', maxLength(255)),
  },
  email: {
    required: helpers.withMessage('El correo electronico es requerido.', required),
    email: helpers.withMessage('Ingrese un correo electronico valido.', email),
    maxLength: helpers.withMessage('El correo electronico no debe superar 255 caracteres.', maxLength(255)),
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

const fillForm = () => {
  form.id = props.employee?.id ?? null
  form.name = props.employee?.name ?? ''
  form.email = props.employee?.email ?? ''
  v$.value.$reset()
}

const resetForm = () => {
  form.id = null
  form.name = ''
  form.email = ''
  v$.value.$reset()
}

const submit = async () => {
  const isValid = await v$.value.$validate()

  if (!isValid) {
    return
  }

  emit('submit', {
    id: form.id,
    name: form.name.trim(),
    email: form.email.trim(),
  })
}

watch(
  () => [props.modelValue, props.employee, props.mode],
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
          Nombre*
        </div>
        <v-text-field
          v-model="form.name"
          density="comfortable"
          :error-messages="v$.name.$errors.map((error) => error.$message)"
          placeholder="Ej: John Doe"
          variant="outlined"
          @blur="v$.name.$touch"
        />
      </div>

      <div>
        <div class="text-primary font-weight-bold mb-1">
          Correo electronico*
        </div>
        <v-text-field
          v-model="form.email"
          density="comfortable"
          :error-messages="v$.email.$errors.map((error) => error.$message)"
          placeholder="Ej: correo@ejemplo.com"
          type="email"
          variant="outlined"
          @blur="v$.email.$touch"
        />
      </div>

      <div v-if="!isEdit" class="d-flex align-center justify-center ga-2 text-secondary text-caption font-weight-bold">
        <v-icon icon="mdi-information-outline" />
        Al crear un empleado se le enviaran al correo sus credenciales de acceso
      </div>
    </div>
  </BaseModal>
</template>
