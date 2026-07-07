<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
})

const STATUS_STYLES = {
  Activo: {
    background: '#a8e6b7',
    border: '#19ad27',
    text: '#19ad27',
  },
  Desactivado: {
    background: '#e79a9c',
    border: '#b9292c',
    text: '#b9292c',
  },
  Inactivo: {
    background: '#e79a9c',
    border: '#b9292c',
    text: '#b9292c',
  },
  Pendiente: {
    background: '#eef5f9',
    border: '#667085',
    text: '#667085'
  },
  Completado: {
    background: '#a8e6b7',
    border: '#19ad27',
    text: '#19ad27',
  },
  Fallo: {
    background: '#e79a9c',
    border: '#b9292c',
    text: '#b9292c',
  },
}

const DEFAULT_STATUS_STYLE = {
  background: '#eef1f6',
  border: '#667085',
  text: '#33415c',
}

const normalizedStatus = computed(() => {
  return props.status.toString().trim()
})

const chipLabel = computed(() => props.label || props.status || '-')

const currentStatusStyle = computed(() => {
  return STATUS_STYLES[normalizedStatus.value] ?? DEFAULT_STATUS_STYLE
})

const chipStyle = computed(() => {
  return {
    '--status-chip-background': currentStatusStyle.value.background,
    '--status-chip-border': currentStatusStyle.value.border,
    '--status-chip-text': currentStatusStyle.value.text,
  }
})
</script>

<template>
  <v-chip
    class="status-chip"
    rounded="pill"
    size="large"
    :style="chipStyle"
    variant="outlined"
  >
    {{ chipLabel }}
  </v-chip>
</template>

<style scoped>
.status-chip {
  background: var(--status-chip-background);
  border-color: var(--status-chip-border);
  border-width: 2px;
  color: var(--status-chip-text);
  font-weight: 800;
  justify-content: center;
  min-width: 160px;
}
</style>
