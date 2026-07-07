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
  user: {
    type: Object,
    default: null,
  },
  roles: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  rolesLoading: {
    type: Boolean,
    default: false,
  },
  rolesError: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue', 'submit', 'cancel'])

const form = reactive({
  id: null,
  name: '',
  email: '',
  role_id: null,
})

const isEditMode = computed(() => props.mode === 'edit')
const title = computed(() => (isEditMode.value ? 'Editar usuario' : 'Crear usuario'))
const acceptText = computed(() => (isEditMode.value ? 'Editar' : 'Crear'))
const acceptDisabled = computed(() =>
  !isEditMode.value && (props.rolesLoading || Boolean(props.rolesError) || props.roles.length === 0),
)
const currentRoleName = computed(() => props.user?.role?.nombre ?? '')

const rules = computed(() => ({
  name: {
    required: helpers.withMessage('El nombre es requerido.', required),
  },
  email: {
    required: helpers.withMessage('El correo electronico es requerido.', required),
    email: helpers.withMessage('Ingrese un correo valido.', email),
  },
  role_id: isEditMode.value
    ? {}
    : {
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

const fillForm = () => {
  form.id = isEditMode.value ? props.user?.id ?? null : null
  form.name = isEditMode.value ? props.user?.name ?? '' : ''
  form.email = isEditMode.value ? props.user?.email ?? '' : ''
  form.role_id = isEditMode.value
    ? props.user?.role?.id ?? props.user?.role_id ?? null
    : null
  v$.value.$reset()
}

const selectRole = (roleId) => {
  form.role_id = roleId
  v$.value.role_id.$touch()
}

const submit = async () => {
  const isValid = await v$.value.$validate()

  if (!isValid || acceptDisabled.value) {
    return
  }

  const payload = {
    id: form.id,
    name: form.name.trim(),
    email: form.email.trim(),
  }

  if (!isEditMode.value) {
    payload.role_id = form.role_id
  }

  emit('submit', payload)
}

watch(
  () => [props.modelValue, props.user, props.mode],
  ([isOpen]) => {
    if (isOpen) {
      fillForm()
    }
  },
  { immediate: true },
)
</script>

<template>
  <BaseModal
    v-model="dialogModel"
    :accept-text="acceptText"
    cancel-text="Cancelar"
    :disabled="acceptDisabled"
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

      <div v-if="isEditMode">
        <div class="text-primary font-weight-bold mb-1">
          Rol actual
        </div>
        <v-text-field
          :model-value="currentRoleName"
          density="comfortable"
          readonly
          variant="outlined"
        />
      </div>

      <div v-else>
        <div class="text-primary font-weight-bold mb-4">
          Seleccionar rol
        </div>

        <v-progress-linear
          v-if="rolesLoading"
          color="primary"
          indeterminate
        />

        <v-alert
          v-else-if="rolesError"
          color="error"
          type="error"
          variant="tonal"
        >
          {{ rolesError }}
        </v-alert>

        <v-alert
          v-else-if="roles.length === 0"
          color="warning"
          type="warning"
          variant="tonal"
        >
          No hay roles disponibles.
        </v-alert>

        <v-row v-else>
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
              @click="selectRole(role.id)"
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

      <div
        v-if="!isEditMode"
        class="d-flex align-center justify-center ga-2 text-secondary text-caption font-weight-bold"
      >
        <v-icon icon="mdi-information-outline" />
        Al crear un usuario se le enviaran al correo sus credenciales de acceso
      </div>
    </div>
  </BaseModal>
</template>
