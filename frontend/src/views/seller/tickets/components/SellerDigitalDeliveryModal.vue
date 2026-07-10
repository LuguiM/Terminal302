<script setup>
import useVuelidate from '@vuelidate/core'
import { email, helpers, required } from '@vuelidate/validators'
import { computed, reactive, watch } from 'vue'

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

const emit = defineEmits(['update:modelValue', 'submit'])

const form = reactive({
  correo_destino: '',
  telefono_destino: '',
})

const rules = computed(() => ({
  correo_destino: {
    required: helpers.withMessage('El correo es obligatorio.', required),
    email: helpers.withMessage('Ingrese un correo valido.', email),
  },
  telefono_destino: {},
}))

const v$ = useVuelidate(rules, form)

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) {
      form.correo_destino = ''
      form.telefono_destino = ''
      v$.value.$reset()
    }
  },
)

const handleSubmit = async () => {
  const valid = await v$.value.$validate()

  if (!valid) {
    return
  }

  emit('submit', {
    correo_destino: form.correo_destino,
    telefono_destino: form.telefono_destino || null,
  })
}
</script>

<template>
  <BaseModal
    :loading="loading"
    :model-value="modelValue"
    title="Envio de ticket digital"
    accept-text="Enviar"
    @accept="handleSubmit"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-form class="d-flex flex-column ga-4">
      <v-text-field
        v-model="form.correo_destino"
        density="comfortable"
        :error-messages="v$.correo_destino.$errors.map((error) => error.$message)"
        label="Correo electronico *"
        placeholder="ejemplo@gmail.com"
        type="email"
        variant="outlined"
        @blur="v$.correo_destino.$touch"
      />

      <v-text-field
        v-model="form.telefono_destino"
        density="comfortable"
        label="Telefono (Opcional)"
        placeholder="+503 1234-5678"
        variant="outlined"
      />
    </v-form>
  </BaseModal>
</template>
