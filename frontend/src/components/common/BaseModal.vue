<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  maxWidth: {
    type: [String, Number],
    default: 640,
  },
  persistent: {
    type: Boolean,
    default: false,
  },
  showCloseButton: {
    type: Boolean,
    default: true,
  },
  showCancelButton: {
    type: Boolean,
    default: true,
  },
  showAcceptButton: {
    type: Boolean,
    default: true,
  },
  cancelText: {
    type: String,
    default: 'Cancelar',
  },
  acceptText: {
    type: String,
    default: 'Aceptar',
  },
  acceptColor: {
    type: String,
    default: 'primary',
  },
  cancelColor: {
    type: String,
    default: 'secondary',
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'close', 'cancel', 'accept'])

const dialogModel = computed({
  get() {
    return props.modelValue
  },
  set(value) {
    emit('update:modelValue', value)
  },
})

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}

const cancel = () => {
  emit('update:modelValue', false)
  emit('cancel')
}
</script>

<template>
  <v-dialog
    v-model="dialogModel"
    :max-width="maxWidth"
    :persistent="persistent"
  >
    <v-card
      class="base-modal pa-4 pa-sm-6"
      rounded="lg"
    >
      <v-btn
        v-if="showCloseButton"
        aria-label="Cerrar modal"
        class="base-modal__close"
        density="comfortable"
        icon="mdi-close"
        variant="text"
        @click="close"
      />

      <v-card-title class="text-center text-primary font-weight-black text-h5 pt-4">
        {{ title }}
      </v-card-title>

      <v-card-text class="px-0 px-sm-2 py-6">
        <slot />
      </v-card-text>

      <v-card-actions class="justify-center ga-4 flex-wrap px-0 pb-2">
        <slot name="actions">
          <v-btn
            v-if="showCancelButton"
            class="base-modal__action"
            :color="cancelColor"
            :disabled="loading"
            rounded="lg"
            variant="outlined"
            @click="cancel"
          >
            {{ cancelText }}
          </v-btn>

          <v-btn
            v-if="showAcceptButton"
            class="base-modal__action"
            :color="acceptColor"
            :disabled="disabled"
            :loading="loading"
            rounded="lg"
            variant="flat"
            @click="$emit('accept')"
          >
            {{ acceptText }}
          </v-btn>
        </slot>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.base-modal {
  position: relative;
}

.base-modal__close {
  position: absolute;
  right: 16px;
  top: 16px;
  z-index: 1;
}

.base-modal__action {
  min-width: min(100%, 190px);
}
</style>
