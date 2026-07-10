<template>
  <section class="py-6 py-md-12">
    <v-container class="public-medium">
      <div class="d-grid align-center mb-8 public-subheader">
        <v-btn
          aria-label="Volver"
          icon="mdi-arrow-left"
          variant="text"
          @click="goBack"
        />
        <h1 class="text-primary text-h5 font-weight-black text-center mb-0">
          Horarios
        </h1>
        <span />
      </div>

      <div class="text-center mb-8">
        <h2 class="text-primary text-h4 font-weight-black mb-1">
          Ruta : {{ route?.ruta || id }}
        </h2>
        <p class="text-primary text-h5 font-weight-bold mb-0">
          {{ route?.denominacion || 'Consultando horarios' }}
        </p>
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
        type="list-item-three-line, list-item-three-line, list-item-three-line"
      />

      <template v-else-if="schedules.length">
        <v-chip-group
          v-model="selectedDayId"
          class="mb-10 justify-center"
          column
          mandatory
          selected-class="bg-greyLigth"
        >
          <v-chip
            v-for="day in availableDays"
            :key="day.id"
            class="font-weight-black"
            :value="day.id"
            variant="outlined"
          >
            {{ day.nombre }}
          </v-chip>
        </v-chip-group>

        <div class="d-grid ga-8">
          <section
            v-for="group in groupedSchedules"
            :key="group.label"
          >
            <h3 class="text-primary text-h5 font-weight-black mb-4 ml-4">
              {{ group.label }}
            </h3>

            <v-card
              v-for="schedule in group.items"
              :key="schedule.horario_id"
              class="mb-4 pa-4 schedule-row"
              elevation="0"
              rounded="lg"
              variant="outlined"
            >
              <div class="d-flex align-center ga-4">
                <v-icon color="primary" icon="mdi-bus-side" size="30" />
                <div class="flex-grow-1">
                  <div class="text-primary font-weight-bold">
                    {{ schedule.operador?.nombre_comercial || 'Operador' }}
                  </div>
                  <div class="text-secondary text-caption">
                    {{ schedule.bus?.placa || 'Unidad por confirmar' }}
                  </div>
                </div>
                <strong class="text-primary text-h5 text-no-wrap">
                  {{ formatTime(schedule.hora_salida) }}
                </strong>
              </div>
            </v-card>
          </section>
        </div>
      </template>

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
import { useRouter } from 'vue-router'

import { getApiErrorMessage } from '@/services/api'
import { getPublicRouteSchedules } from '@/services/publicRouteService'

const props = defineProps({
  id: {
    type: String,
    required: true,
  },
})

const router = useRouter()
const loading = ref(true)
const route = ref(null)
const schedules = ref([])
const errorMessage = ref('')
const selectedDayId = ref(null)

const availableDays = computed(() => {
  const days = new Map()

  schedules.value.forEach((schedule) => {
    if (schedule.dia?.id && !days.has(schedule.dia.id)) {
      days.set(schedule.dia.id, schedule.dia)
    }
  })

  return [...days.values()].sort((a, b) => (a.orden || 0) - (b.orden || 0))
})

const selectedSchedules = computed(() => {
  return schedules.value.filter((schedule) => schedule.dia?.id === selectedDayId.value)
})

const groupedSchedules = computed(() => {
  const groups = [
    {
      label: 'Manana',
      items: [],
    },
    {
      label: 'Tarde',
      items: [],
    },
    {
      label: 'Noche',
      items: [],
    },
  ]

  selectedSchedules.value.forEach((schedule) => {
    const hour = Number.parseInt((schedule.hora_salida || '00:00').slice(0, 2), 10)

    if (hour < 12) {
      groups[0].items.push(schedule)
    } else if (hour < 18) {
      groups[1].items.push(schedule)
    } else {
      groups[2].items.push(schedule)
    }
  })

  return groups.filter((group) => group.items.length)
})

const goBack = () => {
  router.push({ name: 'routes' })
}

const formatTime = (time) => {
  if (!time) {
    return 'No disponible'
  }

  const [rawHour, minute] = time.split(':')
  const hour = Number.parseInt(rawHour, 10)
  const suffix = hour >= 12 ? 'p.m' : 'a.m'
  const normalizedHour = hour % 12 || 12

  return `${normalizedHour.toString().padStart(2, '0')}:${minute} ${suffix}`
}

onMounted(async () => {
  try {
    const response = await getPublicRouteSchedules(props.id)

    route.value = response.ruta
    schedules.value = response.horarios || []
    selectedDayId.value = availableDays.value[0]?.id || null
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error)
  } finally {
    loading.value = false
  }
})
</script>
