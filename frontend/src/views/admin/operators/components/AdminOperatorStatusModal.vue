<script setup>
import { computed, reactive, watch } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { helpers, requiredIf } from '@vuelidate/validators'

import BaseModal from '@/components/common/BaseModal.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  operator: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  isActive: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel'])

const form = reactive({
  motivo_desactivacion: '',
})

const dialogModel = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  },
})

const actionText = computed(() => (props.isActive ? 'Desactivar' : 'Activar'))
const targetStatusText = computed(() => (props.isActive ? 'desactivado' : 'activo'))

const rules = computed(() => ({
  motivo_desactivacion: {
    requiredIf: helpers.withMessage(
      'El motivo de desactivacion es obligatorio.',
      requiredIf(() => props.isActive),
    ),
  },
}))

const v$ = useVuelidate(rules, form, {
  $autoDirty: true,
})

const resetForm = () => {
  form.motivo_desactivacion = ''
  v$.value.$reset()
}

const confirm = async () => {
  const isValid = await v$.value.$validate()

  if (!isValid) {
    return
  }

  emit('confirm', {
    operator: props.operator,
    motivo_desactivacion: form.motivo_desactivacion.trim(),
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
    :accept-text="actionText"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="620"
    :title="`${actionText} operador`"
    @accept="confirm"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
  >
    <div class="text-center text-secondary font-weight-bold d-flex flex-column ga-5">
      <p>
        Esta accion cambiara el estado del operador a {{ targetStatusText }}
      </p>
      <p>&iquest;Desea {{ actionText.toLowerCase() }} al operador?</p>
    </div>

    <div
      v-if="isActive"
      class="mt-6"
    >
      <div class="text-secondary font-weight-bold mb-1">
        Ingrese los motivos para cambiar el estado del operador
      </div>
      <v-textarea
        v-model="form.motivo_desactivacion"
        auto-grow
        density="comfortable"
        :error-messages="v$.motivo_desactivacion.$errors.map((error) => error.$message)"
        placeholder="Motivo"
        rows="4"
        variant="outlined"
        @blur="v$.motivo_desactivacion.$touch"
      />
    </div>
  </BaseModal>
</template>
