<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import PageTitle from '@/components/common/PageTitle.vue'
import {
  getOperatorScheduleRouteDays,
  getOperatorSchedules,
} from '@/services/operatorScheduleService'
import ScheduleDayPanels from '@/views/schedules/components/ScheduleDayPanels.vue'

const route = useRoute()
const router = useRouter()

const routeInfo = ref(null)
const days = ref([])
const loading = ref(false)
const error = ref('')
const sections = reactive({})

const routeId = computed(() => Number(route.params.rutaId))

const ensureSection = (dayId) => {
  const key = String(dayId)

  if (!sections[key]) {
    sections[key] = {
      items: [],
      loading: false,
      error: null,
      loaded: false,
    }
  }

  return sections[key]
}

const fetchInitialData = async () => {
  loading.value = true
  error.value = ''

  try {
    const { data } = await getOperatorScheduleRouteDays(routeId.value)
    routeInfo.value = data.ruta ?? null
    days.value = data.dias ?? []
    days.value.forEach((day) => ensureSection(day.id))
  } catch {
    routeInfo.value = null
    days.value = []
    error.value = 'No se pudo cargar la informacion de horarios. Intente nuevamente.'
  } finally {
    loading.value = false
  }
}

const fetchSchedulesForDay = async (dayId) => {
  const section = ensureSection(dayId)
  section.loading = true
  section.error = null

  try {
    const { data } = await getOperatorSchedules({
      ruta_id: routeId.value,
      dia_id: dayId,
    })

    section.items = data.horarios ?? []
    section.loaded = true
  } catch {
    section.items = []
    section.error = 'No se pudieron cargar los horarios. Intente nuevamente.'
  } finally {
    section.loading = false
  }
}

const handleOpenDay = (dayId) => {
  const section = ensureSection(dayId)

  if (!section.loaded && !section.loading) {
    fetchSchedulesForDay(dayId)
  }
}

const goBack = () => {
  router.push({ name: 'operator-schedules' })
}

onMounted(fetchInitialData)
</script>

<template>
  <v-container class="operator-schedule-route-view" fluid>
    <v-btn
      class="mb-6 px-8"
      prepend-icon="mdi-arrow-left"
      rounded="lg"
      variant="outlined"
      @click="goBack"
    >
      Volver
    </v-btn>

    <PageTitle title="Horarios" />

    <v-progress-linear
      v-if="loading"
      class="mt-6"
      color="primary"
      indeterminate
    />

    <v-alert
      v-if="error"
      class="mt-6"
      color="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <ScheduleDayPanels
      v-if="routeInfo"
      class="mt-10"
      :days="days"
      readonly
      :route="routeInfo"
      :sections="sections"
      @open-day="handleOpenDay"
    />
  </v-container>
</template>

<style scoped>
.operator-schedule-route-view {
  color: rgb(var(--v-theme-primary));
}
</style>
