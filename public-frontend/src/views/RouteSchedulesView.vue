<template>
  <section class="public-page">
    <v-container class="public-page__container public-page__container--wide">
      <div class="page-heading">
        <p class="eyebrow">Horarios de ruta</p>
        <h1>{{ routeTitle }}</h1>
        <p>Revisa las salidas activas, operadores y unidades asignadas a la ruta.</p>
      </div>

      <v-alert
        v-if="errorMessage"
        class="mb-6"
        type="error"
        variant="tonal"
      >
        {{ errorMessage }}
      </v-alert>

      <v-skeleton-loader
        v-if="loading"
        type="table"
      />

      <v-table v-else-if="schedules.length" class="schedule-table">
        <thead>
          <tr>
            <th>Dia</th>
            <th>Hora</th>
            <th>Operador</th>
            <th>Unidad</th>
            <th>Tarifa</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="schedule in schedules" :key="schedule.horario_id">
            <td>{{ schedule.dia?.nombre || 'No disponible' }}</td>
            <td>{{ schedule.hora_salida }}</td>
            <td>{{ schedule.operador?.nombre_comercial || 'No disponible' }}</td>
            <td>{{ schedule.bus?.placa || 'No disponible' }}</td>
            <td>${{ schedule.tarifa }}</td>
          </tr>
        </tbody>
      </v-table>

      <v-empty-state
        v-else
        headline="Sin horarios publicados"
        icon="mdi-calendar-clock"
        text="No se encontraron horarios activos para esta ruta."
      />
    </v-container>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'

import { getApiErrorMessage } from '@/services/api'
import { getPublicRouteSchedules } from '@/services/publicRouteService'

const props = defineProps({
  id: {
    type: String,
    required: true,
  },
})

const loading = ref(true)
const route = ref(null)
const schedules = ref([])
const errorMessage = ref('')

const routeTitle = computed(() => {
  if (!route.value) {
    return `Ruta ${props.id}`
  }

  return `${route.value.ruta} - ${route.value.denominacion}`
})

onMounted(async () => {
  try {
    const response = await getPublicRouteSchedules(props.id)

    route.value = response.ruta
    schedules.value = response.horarios || []
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error)
  } finally {
    loading.value = false
  }
})
</script>
