<script setup>
import { useUiStore } from '@/stores/uiStore'

const uiStore = useUiStore()
</script>

<template>
  <v-overlay
    class="d-flex align-center justify-center"
    contained
    persistent
    :model-value="uiStore.isLoading"
    :z-index="3000"
  >
    <v-sheet
      class="d-flex align-center justify-center rounded-circle"
      color="transparent"
      elevation="8"
      height="120"
      role="status"
      aria-label="Cargando"
      width="120"
    >
      <v-progress-circular
        color="primary"
        indeterminate
        size="72"
        width="6"
      />
    </v-sheet>
  </v-overlay>
</template>
