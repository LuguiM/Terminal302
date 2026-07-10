<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { helpers, required } from '@vuelidate/validators'
import { useDisplay } from 'vuetify'

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
  schedule: {
    type: Object,
    default: null,
  },
  days: {
    type: Array,
    default: () => [],
  },
  operators: {
    type: Array,
    default: () => [],
  },
  buses: {
    type: Array,
    default: () => [],
  },
  busesLoading: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'submit', 'cancel', 'operator-change'])

const { smAndDown } = useDisplay()

const form = reactive({
  id: null,
  dia_id: null,
  hora_salida: '',
  operador_id: null,
  bus_id: null,
  sobreventa_permitida: false,
})

const timePickerOpen = ref(false)

const dialogModel = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  },
})

const title = computed(() => (props.mode === 'edit' ? 'Editar horario' : 'Agregar horario'))
const acceptText = computed(() => (props.mode === 'edit' ? 'Editar' : 'Agregar'))

const timeLabel = computed(() => {
  if (!form.hora_salida) {
    return ''
  }

  const [hoursValue, minutes = '00'] = form.hora_salida.split(':')
  const hours = Number(hoursValue)

  if (Number.isNaN(hours)) {
    return form.hora_salida
  }

  const period = hours >= 12 ? 'PM' : 'AM'
  const displayHours = hours % 12 || 12

  return `${String(displayHours).padStart(2, '0')}:${minutes} ${period}`
})

const formatBusTitle = (bus) => {
  if (!bus) {
    return ''
  }

  return `${bus.placa ?? ''} - ${bus.marca ?? ''}`.trim()
}

const rules = computed(() => ({
  dia_id: {
    required: helpers.withMessage('Seleccione un dia.', required),
  },
  hora_salida: {
    required: helpers.withMessage('La hora de salida es requerida.', required),
    time: helpers.withMessage(
      'La hora de salida debe tener formato HH:mm.',
      helpers.regex(/^([01]\d|2[0-3]):[0-5]\d$/),
    ),
  },
  operador_id: {
    required: helpers.withMessage('Seleccione un operador.', required),
  },
  bus_id: {
    required: helpers.withMessage('Seleccione una unidad.', required),
  },
}))

const v$ = useVuelidate(rules, form, {
  $autoDirty: true,
})

const resetForm = () => {
  form.id = props.schedule?.id ?? null
  form.dia_id = props.schedule?.dia?.id ?? null
  form.hora_salida = props.schedule?.hora_salida ?? ''
  form.operador_id = props.schedule?.operador?.id ?? null
  form.bus_id = props.schedule?.bus?.id ?? null
  form.sobreventa_permitida = Boolean(props.schedule?.sobreventa_permitida)
  v$.value.$reset()
}

const updateTime = (value) => {
  form.hora_salida = value ?? ''
  v$.value.hora_salida.$touch()
}

const confirm = async () => {
  const isValid = await v$.value.$validate()

  if (!isValid) {
    return
  }

  emit('submit', {
    id: form.id,
    dia_id: form.dia_id,
    hora_salida: form.hora_salida,
    operador_id: form.operador_id,
    bus_id: form.bus_id,
    sobreventa_permitida: Boolean(form.sobreventa_permitida),
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

watch(
  () => props.schedule,
  () => {
    if (props.modelValue) {
      resetForm()
    }
  },
)

watch(
  () => form.operador_id,
  (operatorId, previousOperatorId) => {
    if (!props.modelValue || !operatorId) {
      return
    }

    if (previousOperatorId && operatorId !== previousOperatorId) {
      form.bus_id = null
    }

    emit('operator-change', operatorId)
  },
)
</script>

<template>
  <BaseModal
    v-model="dialogModel"
    :accept-text="acceptText"
    cancel-text="Cancelar"
    :loading="loading"
    max-width="620"
    :title="title"
    @accept="confirm"
    @cancel="$emit('cancel')"
    @close="$emit('cancel')"
  >
    <div class="d-flex flex-column ga-4">
      <v-autocomplete
        v-model="form.dia_id"
        density="comfortable"
        :error-messages="v$.dia_id.$errors.map((error) => error.$message)"
        item-title="nombre"
        item-value="id"
        :items="days"
        label="Dia"
        placeholder="Selecciona un dia"
        variant="outlined"
        @blur="v$.dia_id.$touch"
      />

      <v-text-field
        v-if="smAndDown"
        v-model="form.hora_salida"
        density="comfortable"
        :error-messages="v$.hora_salida.$errors.map((error) => error.$message)"
        label="Hora de salida"
        placeholder="--:--"
        prepend-inner-icon="mdi-clock-outline"
        type="time"
        variant="outlined"
        @blur="v$.hora_salida.$touch"
      />

      <v-text-field
        v-else
        density="comfortable"
        :error-messages="v$.hora_salida.$errors.map((error) => error.$message)"
        label="Hora de salida"
        :model-value="timeLabel"
        placeholder="--:-- AM"
        prepend-inner-icon="mdi-clock-outline"
        readonly
        variant="outlined"
        @blur="v$.hora_salida.$touch"
      >
        <v-menu
          v-model="timePickerOpen"
          activator="parent"
          :close-on-content-click="false"
          min-width="0"
        >
          <v-time-picker
            color="primary"
            format="ampm"
            :model-value="form.hora_salida"
            title="Hora de salida"
            @update:model-value="updateTime"
          >
            <template #actions>
              <v-spacer />

              <v-btn
                color="primary"
                variant="text"
                @click="timePickerOpen = false"
              >
                Aceptar
              </v-btn>
            </template>
          </v-time-picker>
        </v-menu>
      </v-text-field>

      <v-autocomplete
        v-model="form.operador_id"
        density="comfortable"
        :error-messages="v$.operador_id.$errors.map((error) => error.$message)"
        item-title="nombre_comercial"
        item-value="id"
        :items="operators"
        label="Operador"
        placeholder="Seleccionar operador"
        variant="outlined"
        @blur="v$.operador_id.$touch"
      />

      <v-autocomplete
        v-model="form.bus_id"
        density="comfortable"
        :disabled="!form.operador_id"
        :error-messages="v$.bus_id.$errors.map((error) => error.$message)"
        :item-title="formatBusTitle"
        item-value="id"
        :items="buses"
        label="Unidad de transporte"
        :loading="busesLoading"
        placeholder="Seleccionar unidad"
        variant="outlined"
        @blur="v$.bus_id.$touch"
      >
        <template #item="{ props: itemProps, item }">
          <v-list-item
            v-bind="itemProps"
            :subtitle="item.nombre_unidad"
            :title="`${item.placa} - ${item.marca}`"
          />
        </template>

        <template #selection="{ item }">
          {{ item.placa }} - {{ item.marca }}
        </template>
      </v-autocomplete>

      <v-checkbox
        v-model="form.sobreventa_permitida"
        color="primary"
        density="comfortable"
        hide-details
        label="Permitir sobreventa"
      />
    </div>
  </BaseModal>
</template>
