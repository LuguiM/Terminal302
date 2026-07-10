<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  errorMessage: {
    type: String,
    default: '',
  },
  hasDetails: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'submit', 'show-details'])

const codigoTicket = ref('')

const sheetModel = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  },
})

const canSubmit = computed(() => codigoTicket.value.trim().length > 0 && !props.loading)

const submit = () => {
  if (!canSubmit.value) {
    return
  }

  emit('submit', codigoTicket.value.trim())
}

const cancel = () => {
  sheetModel.value = false
}

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) {
      codigoTicket.value = ''
    }
  },
)
</script>

<template>
  <v-bottom-sheet v-model="sheetModel">
    <v-card
      class="mx-auto pa-5 pa-sm-7 validator-code-sheet"
      max-width="460"
      rounded="xl"
    >
      <v-card-title class="text-center text-primary text-h5 font-weight-black mb-6">
        Validador
      </v-card-title>

      <v-card-text class="pa-0">
        <v-text-field
          v-model="codigoTicket"
          autofocus
          density="comfortable"
          :disabled="loading"
          label="Codigo de ticket"
          placeholder="TKT-20260707-XXXX"
          variant="outlined"
          @keyup.enter="submit"
        />

        <v-alert
          v-if="errorMessage"
          class="mb-4"
          color="error"
          variant="tonal"
        >
          <div class="d-flex flex-column ga-3">
            <span class="text-body-1 font-weight-medium">{{ errorMessage }}</span>
            <v-btn
              v-if="hasDetails"
              color="error"
              prepend-icon="mdi-alert-circle-outline"
              rounded="lg"
              size="large"
              variant="flat"
              @click="$emit('show-details')"
            >
              Ver detalles
            </v-btn>
          </div>
        </v-alert>
      </v-card-text>

      <v-card-actions class="pa-0 pt-4 d-flex flex-column ga-3">
        <v-btn
          block
          color="primary"
          :disabled="!canSubmit"
          :loading="loading"
          rounded="lg"
          size="large"
          variant="flat"
          @click="submit"
        >
          Validar
        </v-btn>

        <v-btn
          block
          color="secondary"
          :disabled="loading"
          rounded="lg"
          size="large"
          variant="outlined"
          @click="cancel"
        >
          Cancelar
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-bottom-sheet>
</template>

<style scoped>
.validator-code-sheet {
  margin-bottom: 16px;
  width: calc(100% - 24px);
}
</style>
