<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import PageTitle from '@/components/common/PageTitle.vue'
import { notify } from '@/services/notifyService'
import {
  createAdminSchedule,
  deleteAdminSchedule,
  getAdminScheduleBuses,
  getAdminScheduleDays,
  getAdminScheduleRouteDays,
  getAdminScheduleRouteOperators,
  getAdminSchedules,
  updateAdminSchedule,
} from '@/services/adminScheduleService'
import AdminScheduleDeleteModal from '@/views/admin/schedules/components/AdminScheduleDeleteModal.vue'
import AdminScheduleFormModal from '@/views/admin/schedules/components/AdminScheduleFormModal.vue'
import ScheduleDayPanels from '@/views/schedules/components/ScheduleDayPanels.vue'

const route = useRoute()
const router = useRouter()

const routeInfo = ref(null)
const days = ref([])
const operators = ref([])
const buses = ref([])
const loading = ref(false)
const busesLoading = ref(false)
const error = ref('')
const actionLoading = ref(false)
const selectedSchedule = ref(null)
const formMode = ref('create')
const showFormModal = ref(false)
const showDeleteModal = ref(false)

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
    const [routeResponse, daysResponse, operatorsResponse] = await Promise.all([
      getAdminScheduleRouteDays(routeId.value),
      getAdminScheduleDays(),
      getAdminScheduleRouteOperators(routeId.value),
    ])

    routeInfo.value = routeResponse.data.ruta ?? null
    days.value = daysResponse.data.dias ?? []
    operators.value = operatorsResponse.data.operadores ?? []
    days.value.forEach((day) => ensureSection(day.id))
  } catch {
    routeInfo.value = null
    days.value = []
    operators.value = []
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
    const { data } = await getAdminSchedules({
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

const fetchBuses = async (operatorId) => {
  if (!operatorId) {
    buses.value = []
    return
  }

  busesLoading.value = true

  try {
    const { data } = await getAdminScheduleBuses({
      ruta_id: routeId.value,
      operador_id: operatorId,
    })

    buses.value = data.buses ?? []
  } catch {
    buses.value = []
  } finally {
    busesLoading.value = false
  }
}

const openCreateModal = () => {
  selectedSchedule.value = null
  formMode.value = 'create'
  buses.value = []
  showFormModal.value = true
}

const openEditModal = (schedule) => {
  selectedSchedule.value = schedule
  formMode.value = 'edit'
  buses.value = []
  showFormModal.value = true
  fetchBuses(schedule.operador?.id)
}

const openDeleteModal = (schedule) => {
  selectedSchedule.value = schedule
  showDeleteModal.value = true
}

const closeModals = () => {
  showFormModal.value = false
  showDeleteModal.value = false
  selectedSchedule.value = null
  buses.value = []
}

const refreshAffectedDays = async (dayIds) => {
  const uniqueDayIds = [...new Set(dayIds.filter(Boolean))]

  await Promise.all(
    uniqueDayIds.map((dayId) => fetchSchedulesForDay(dayId)),
  )
}

const handleSaveSchedule = async (payload) => {
  actionLoading.value = true
  const previousDayId = selectedSchedule.value?.dia?.id

  try {
    const requestPayload = {
      ruta_id: routeId.value,
      dia_id: payload.dia_id,
      hora_salida: payload.hora_salida,
      operador_id: payload.operador_id,
      bus_id: payload.bus_id,
      sobreventa_permitida: payload.sobreventa_permitida,
    }
    const request = payload.id
      ? updateAdminSchedule(payload.id, requestPayload)
      : createAdminSchedule(requestPayload)

    const { data } = await request
    notify.success(data.message || 'Horario guardado correctamente.')
    closeModals()
    await refreshAffectedDays([payload.dia_id, previousDayId])
  } finally {
    actionLoading.value = false
  }
}

const handleDeleteSchedule = async () => {
  if (!selectedSchedule.value?.id) {
    return
  }

  actionLoading.value = true
  const dayId = selectedSchedule.value?.dia?.id

  try {
    const { data } = await deleteAdminSchedule(selectedSchedule.value.id)
    notify.success(data.message || 'Horario eliminado correctamente.')
    closeModals()
    await refreshAffectedDays([dayId])
  } finally {
    actionLoading.value = false
  }
}

const goBack = () => {
  router.push({ name: 'admin-schedules' })
}

onMounted(fetchInitialData)
</script>

<template>
  <v-container class="admin-schedule-management-view" fluid>
    <v-btn
      class="mb-6 px-8"
      prepend-icon="mdi-arrow-left"
      rounded="lg"
      variant="outlined"
      @click="goBack"
    >
      Volver
    </v-btn>

    <PageTitle title="Gestion de horarios" />

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
      :route="routeInfo"
      :sections="sections"
      @delete="openDeleteModal"
      @edit="openEditModal"
      @open-day="handleOpenDay"
    >
      <template #actions>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          size="large"
          @click="openCreateModal"
        >
          Agregar horario
        </v-btn>
      </template>
    </ScheduleDayPanels>

    <AdminScheduleFormModal
      v-model="showFormModal"
      :buses="buses"
      :buses-loading="busesLoading"
      :days="days"
      :loading="actionLoading"
      :mode="formMode"
      :operators="operators"
      :schedule="selectedSchedule"
      @cancel="closeModals"
      @operator-change="fetchBuses"
      @submit="handleSaveSchedule"
    />

    <AdminScheduleDeleteModal
      v-model="showDeleteModal"
      :loading="actionLoading"
      :schedule="selectedSchedule"
      @cancel="closeModals"
      @confirm="handleDeleteSchedule"
    />
  </v-container>
</template>

<style scoped>
.admin-schedule-management-view {
  color: rgb(var(--v-theme-primary));
}
</style>
