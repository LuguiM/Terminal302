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
  roles: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'submit', 'cancel'])

const form = reactive({
  name: '',
  email: '',
  role_id: null,
})

const rules = computed(() => ({
  name: {
    required: helpers.withMessage('El nombre es requerido.', required),
  },
  email: {
    required: helpers.withMessage('El correo electronico es requerido.', required),
    email: helpers.withMessage('Ingrese un correo valido.', email),
  },
  role_id: {
    required: helpers.withMessage('Seleccione un rol.', required),
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
  form.name = ''
  form.email = ''
  form.role_id = null
  v$.value.$reset()
}

const submit = async () => {
  const isValid = await v$.value.$validate()

  if (!isValid) {
    return
  }

  emit('submit', {
    name: form.name.trim(),
    email: form.email.trim(),
    role_id: form.role_id,
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
    accept-text="Crear"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="600"
    title="Crear usuario"
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
          placeholder="Ej: Juan Perez"
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
          placeholder="Ej: juan.perez@admin.com"
          type="email"
          variant="outlined"
          @blur="v$.email.$touch"
        />
      </div>

      <div>
        <div class="text-primary font-weight-bold mb-4">
          Seleccionar rol
        </div>
        <v-row dense>
          <v-col
            v-for="role in roles"
            :key="role.id"
            cols="6"
            sm="4"
          >
            <v-btn
              block
              :color="form.role_id === role.id ? 'primary' : 'secondary'"
              rounded="lg"
              :variant="form.role_id === role.id ? 'flat' : 'outlined'"
              @click="form.role_id = role.id; v$.role_id.$touch()"
            >
              {{ role.nombre }}
            </v-btn>
          </v-col>
        </v-row>
        <div
          v-if="v$.role_id.$errors.length"
          class="text-error text-caption mt-1"
        >
          {{ v$.role_id.$errors[0].$message }}
        </div>
      </div>

      <div class="d-flex align-center justify-center ga-2 text-secondary text-caption font-weight-bold">
        <v-icon icon="mdi-information-outline" />
        Al crear un usuario se le enviaran al correo sus credenciales de acceso
      </div>
    </div>
  </BaseModal>
</template>
