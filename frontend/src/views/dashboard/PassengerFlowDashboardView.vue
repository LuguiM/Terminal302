<script setup>
import {
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Title,
  Tooltip,
} from 'chart.js'
import { Bar, Line } from 'vue-chartjs'
import { computed, onMounted, ref, watch } from 'vue'

import PageTitle from '@/components/common/PageTitle.vue'
import { getAdminOperators } from '@/services/adminOperatorService'
import { getAdminScheduleRoutes } from '@/services/adminScheduleService'
import {
  getAdminPassengerFlow,
  getOperatorPassengerFlow,
} from '@/services/dashboardService'
import { getOperatorBuses } from '@/services/operatorBusService'
import { getOperatorScheduleRoutes } from '@/services/operatorScheduleService'
import { useAuthStore } from '@/stores/authStore'

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
)

const props = defineProps({
  scope: {
    type: String,
    default: 'auto',
  },
})

const authStore = useAuthStore()

const loading = ref(false)
const filtersLoading = ref(false)
const error = ref('')
const dashboard = ref(null)
const filterMode = ref('fecha')
const singleDate = ref(formatInputDate(new Date()))
const startDate = ref(formatInputDate(new Date()))
const endDate = ref(formatInputDate(new Date()))
const month = ref(formatInputMonth(new Date()))
const routeId = ref(null)
const operatorId = ref(null)
const busId = ref(null)
const routes = ref([])
const operators = ref([])
const buses = ref([])

const roleName = computed(() =>
  authStore.user?.role?.nombre?.toString().trim().toLowerCase() ?? '',
)

const resolvedScope = computed(() => {
  if (props.scope !== 'auto') {
    return props.scope
  }

  if (roleName.value === 'administrador') {
    return 'admin'
  }

  if (roleName.value === 'empresario') {
    return 'operator'
  }

  return 'unsupported'
})

const isAdminDashboard = computed(() => resolvedScope.value === 'admin')
const isOperatorDashboard = computed(() => resolvedScope.value === 'operator')

const title = computed(() =>
  isAdminDashboard.value
    ? 'Dashboard administrativo'
    : 'Dashboard de operador',
)

const subtitle = computed(() =>
  isAdminDashboard.value
    ? 'Flujo global de pasajeros vendidos y validados.'
    : 'Flujo de pasajeros de tus rutas y unidades.',
)

const routeOptions = computed(() =>
  routes.value.map((route) => ({
    title: `${route.ruta}`,
    value: route.id,
  })),
)

const operatorOptions = computed(() =>
  operators.value.map((operator) => ({
    title: operator.nombre_comercial || operator.razon_social || `Operador ${operator.id}`,
    value: operator.id,
  })),
)

const busOptions = computed(() =>
  buses.value.map((bus) => ({
    title: `${bus.placa} - ${bus.nombre_unidad || bus.marca || 'Unidad'}`,
    value: bus.id,
  })),
)

const summary = computed(() => dashboard.value?.resumen ?? {})
const dailySeries = computed(() => dashboard.value?.series?.por_dia ?? [])
const rankings = computed(() => dashboard.value?.rankings ?? {})

const scheduleHeaders = [
  { title: 'Ruta', key: 'ruta' },
  { title: 'Salida', key: 'hora_salida' },
  { title: 'Vendidos', key: 'tickets_vendidos', align: 'end' },
  { title: 'Validados', key: 'tickets_validados', align: 'end' },
]

const flowHeaders = computed(() => [
  {
    title: isAdminDashboard.value ? 'Operador' : 'Unidad',
    key: isAdminDashboard.value ? 'nombre_comercial' : 'placa',
  },
  { title: 'Vendidos', key: 'tickets_vendidos', align: 'end' },
  { title: 'Validados', key: 'tickets_validados', align: 'end' },
  { title: 'Sobreventa', key: 'tickets_sobreventa', align: 'end' },
])

const kpis = computed(() => [
  {
    label: 'Vendidos',
    value: formatNumber(summary.value.tickets_vendidos),
    icon: 'mdi-ticket-confirmation',
  },
  {
    label: 'Validados',
    value: formatNumber(summary.value.tickets_validados),
    icon: 'mdi-qrcode-scan',
  },
  {
    label: 'Validación',
    value: `${formatNumber(summary.value.porcentaje_validacion)}%`,
    icon: 'mdi-percent',
  },
  {
    label: 'Sobreventa',
    value: formatNumber(summary.value.tickets_sobreventa),
    icon: 'mdi-alert-circle-outline',
  },
  {
    label: 'Salidas',
    value: formatNumber(summary.value.salidas_operadas),
    icon: 'mdi-bus-clock',
  },
  {
    label: 'Ocupación',
    value: `${formatNumber(summary.value.ocupacion_promedio)}%`,
    icon: 'mdi-seat-passenger',
  },
])

const lineChartData = computed(() => ({
  labels: dailySeries.value.map((item) => formatShortDate(item.fecha)),
  datasets: [
    {
      label: 'Vendidos',
      data: dailySeries.value.map((item) => item.tickets_vendidos),
      borderColor: '#005fd1',
      backgroundColor: 'rgba(0, 95, 209, 0.16)',
      tension: 0.25,
      fill: true,
    },
    {
      label: 'Validados',
      data: dailySeries.value.map((item) => item.tickets_validados),
      borderColor: '#19AD27',
      backgroundColor: 'rgba(25, 173, 39, 0.14)',
      tension: 0.25,
      fill: true,
    },
  ],
}))

const barChartData = computed(() => ({
  labels: (rankings.value.rutas ?? []).map((item) => item.ruta),
  datasets: [
    {
      label: 'Vendidos',
      data: (rankings.value.rutas ?? []).map((item) => item.tickets_vendidos),
      backgroundColor: '#023E7D',
      borderRadius: 4,
    },
    {
      label: 'Validados',
      data: (rankings.value.rutas ?? []).map((item) => item.tickets_validados),
      backgroundColor: '#19AD27',
      borderRadius: 4,
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      labels: {
        boxWidth: 14,
        color: '#33415C',
      },
    },
  },
  scales: {
    x: {
      ticks: {
        color: '#33415C',
      },
      grid: {
        display: false,
      },
    },
    y: {
      beginAtZero: true,
      ticks: {
        precision: 0,
        color: '#33415C',
      },
    },
  },
}

const hasData = computed(() => Number(summary.value.tickets_vendidos ?? 0) > 0)

function formatInputDate(date) {
  const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000)

  return localDate.toISOString().slice(0, 10)
}

function formatInputMonth(date) {
  return formatInputDate(date).slice(0, 7)
}

function formatNumber(value) {
  return new Intl.NumberFormat('es-SV', {
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0))
}

function formatShortDate(value) {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('es-SV', {
    day: '2-digit',
    month: 'short',
  }).format(new Date(`${value}T00:00:00`))
}

function formatTime(value) {
  if (!value) {
    return '-'
  }

  const [hours, minutes] = value.toString().split(':')

  return `${hours}:${minutes}`
}

function buildParams() {
  const params = {
    ruta_id: routeId.value || undefined,
  }

  if (filterMode.value === 'fecha') {
    params.fecha = singleDate.value || undefined
  }

  if (filterMode.value === 'rango') {
    params.fecha_desde = startDate.value || undefined
    params.fecha_hasta = endDate.value || undefined
  }

  if (filterMode.value === 'mes') {
    params.mes = month.value || undefined
  }

  if (isAdminDashboard.value) {
    params.operador_id = operatorId.value || undefined
  }

  if (isOperatorDashboard.value) {
    params.bus_id = busId.value || undefined
  }

  return params
}

async function fetchFilterOptions() {

  filtersLoading.value = true

  try {
    if (isAdminDashboard.value) {
      const [routesResponse, operatorsResponse] = await Promise.all([
        getAdminScheduleRoutes(),
        getAdminOperators({ per_page: 50 }),
      ])

      routes.value = routesResponse.data.rutas ?? []
      operators.value = operatorsResponse.data.operadores ?? []
      buses.value = []

      return
    }

    const [routesResponse, busesResponse] = await Promise.all([
      getOperatorScheduleRoutes(),
      getOperatorBuses({ per_page: 50 }),
    ])

    routes.value = routesResponse.data.rutas ?? []
    buses.value = busesResponse.data.buses ?? []
    operators.value = []
  } finally {
    filtersLoading.value = false
  }
}

async function fetchDashboard() {

  loading.value = true
  error.value = ''

  try {
    const request = isAdminDashboard.value
      ? getAdminPassengerFlow
      : getOperatorPassengerFlow
    const { data } = await request(buildParams())

    dashboard.value = data
  } catch (requestError) {
    dashboard.value = null
    error.value = requestError?.response?.data?.message || 'No se pudo cargar el dashboard.'
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  filterMode.value = 'fecha'
  singleDate.value = formatInputDate(new Date())
  startDate.value = formatInputDate(new Date())
  endDate.value = formatInputDate(new Date())
  month.value = formatInputMonth(new Date())
  routeId.value = null
  operatorId.value = null
  busId.value = null
  fetchDashboard()
}

watch(resolvedScope, async () => {
  await fetchFilterOptions()
  await fetchDashboard()
})

onMounted(async () => {
  authStore.loadSession()
  await fetchFilterOptions()
  await fetchDashboard()
})
</script>

<template>
  <v-container
    class="text-primary"
    fluid
  >
      <PageTitle :title="title" />

      <p class="text-secondary mt-n7 mb-7 text-center">
        {{ subtitle }}
      </p>

      <v-card
        class="bg-surface mb-6"
        rounded="lg"
        variant="outlined"
      >
        <v-card-text>
          <v-row align="center">
            <v-col
              cols="12"
              :md="filterMode === 'rango' ? 3 : 2"
            >
              <v-select
                v-model="filterMode"
                density="comfortable"
                hide-details
                :items="[
                  { title: 'Día', value: 'fecha' },
                  { title: 'Rango', value: 'rango' },
                  { title: 'Mes', value: 'mes' },
                ]"
                label="Periodo"
                variant="outlined"
              />
            </v-col>

            <v-col
              v-if="filterMode === 'fecha'"
              cols="12"
              sm="6"
              md="2"
            >
              <v-text-field
                v-model="singleDate"
                density="comfortable"
                hide-details
                label="Fecha"
                type="date"
                variant="outlined"
              />
            </v-col>

            <template v-if="filterMode === 'rango'">
              <v-col cols="12" sm="6" md="2">
                <v-text-field
                  v-model="startDate"
                  density="comfortable"
                  hide-details
                  label="Desde"
                  type="date"
                  variant="outlined"
                />
              </v-col>

              <v-col cols="12" sm="6" md="2">
                <v-text-field
                  v-model="endDate"
                  density="comfortable"
                  hide-details
                  label="Hasta"
                  type="date"
                  variant="outlined"
                />
              </v-col>
            </template>

            <v-col
              v-if="filterMode === 'mes'"
              cols="12"
              sm="6"
              md="2"
            >
              <v-text-field
                v-model="month"
                density="comfortable"
                hide-details
                label="Mes"
                type="month"
                variant="outlined"
              />
            </v-col>

            <v-col cols="12" md="2">
              <v-select
                v-model="routeId"
                clearable
                density="comfortable"
                hide-details
                :items="routeOptions"
                label="Ruta"
                :loading="filtersLoading"
                variant="outlined"
              />
            </v-col>

            <v-col
              v-if="isAdminDashboard"
              cols="12"
              :md="filterMode === 'rango' ? 3 : 2"
            >
              <v-select
                v-model="operatorId"
                clearable
                density="comfortable"
                hide-details
                :items="operatorOptions"
                label="Operador"
                :loading="filtersLoading"
                variant="outlined"
              />
            </v-col>

            <v-col
              v-if="isOperatorDashboard"
              cols="12"
              :md="filterMode === 'rango' ? 3 : 2"
            >
              <v-select
                v-model="busId"
                clearable
                density="comfortable"
                hide-details
                :items="busOptions"
                label="Unidad"
                :loading="filtersLoading"
                variant="outlined"
              />
            </v-col>

            <v-col
              cols="12"
              sm="6"
              md="4"
            >
              <div class="d-flex ga-4">
                <v-btn
                  class="flex-grow-1"
                  color="primary"
                  :loading="loading"
                  prepend-icon="mdi-filter-check"
                  @click="fetchDashboard"
                >
                  Aplicar
                </v-btn>

                <v-btn
                  class="flex-grow-1"
                  variant="outlined"
                  @click="clearFilters"
                >
                  Limpiar
                </v-btn>
              </div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <v-alert
        v-if="error"
        class="mb-5"
        color="error"
        type="error"
        variant="tonal"
      >
        {{ error }}
      </v-alert>

      <v-row class="mb-2">
        <v-col
          v-for="kpi in kpis"
          :key="kpi.label"
          cols="12"
          sm="6"
          lg="2"
        >
          <v-card
            class="h-100"
            min-height="104"
            rounded="lg"
            variant="outlined"
          >
            <v-card-text class="d-flex align-center ga-4 h-100">
              <v-icon
                color="blueLigth"
                :icon="kpi.icon"
                size="28"
              />
              <div>
                <div class="text-secondary text-subtitle-2 font-weight-bold">
                  {{ kpi.label }}
                </div>
                <div class="text-primary text-h5 font-weight-black">
                  {{ kpi.value }}
                </div>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-alert
        v-if="!loading && !hasData"
        class="mb-6"
        color="info"
        type="info"
        variant="tonal"
      >
        No hay flujo de pasajeros para el periodo seleccionado.
      </v-alert>

      <v-row class="mb-6">
        <v-col cols="12" lg="7">
          <v-card
            rounded="lg"
            variant="outlined"
          >
            <v-card-title class="text-primary text-subtitle-1 font-weight-black">
              Vendidos vs validados
            </v-card-title>
            <v-card-text class="dashboard-chart-body">
              <Line
                aria-label="Gráfica de tickets vendidos y validados por día"
                :data="lineChartData"
                :options="chartOptions"
              />
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" lg="5">
          <v-card
            rounded="lg"
            variant="outlined"
          >
            <v-card-title class="text-primary text-subtitle-1 font-weight-black">
              Rutas con mayor flujo
            </v-card-title>
            <v-card-text class="dashboard-chart-body">
              <Bar
                aria-label="Gráfica de rutas con más tickets vendidos y validados"
                :data="barChartData"
                :options="chartOptions"
              />
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" lg="6">
          <v-card
            rounded="lg"
            variant="outlined"
          >
            <v-card-title class="text-primary text-subtitle-1 font-weight-black">
              Horarios con mayor flujo
            </v-card-title>
            <v-data-table
              density="comfortable"
              :headers="scheduleHeaders"
              hide-default-footer
              :items="rankings.horarios ?? []"
              :items-per-page="-1"
              item-value="horario_id"
              no-data-text="Sin datos para mostrar."
            >
              <template #item.hora_salida="{ value }">
                {{ formatTime(value) }}
              </template>
              <template #item.tickets_vendidos="{ value }">
                {{ formatNumber(value) }}
              </template>
              <template #item.tickets_validados="{ value }">
                {{ formatNumber(value) }}
              </template>
            </v-data-table>
          </v-card>
        </v-col>

        <v-col cols="12" lg="6">
          <v-card
            rounded="lg"
            variant="outlined"
          >
            <v-card-title class="text-primary text-subtitle-1 font-weight-black">
              {{ isAdminDashboard ? 'Operadores con mayor flujo' : 'Unidades con mayor uso' }}
            </v-card-title>
            <v-data-table
              density="comfortable"
              :headers="flowHeaders"
              hide-default-footer
              :items="(isAdminDashboard ? rankings.operadores : rankings.buses) ?? []"
              :items-per-page="-1"
              :item-value="isAdminDashboard ? 'operador_id' : 'bus_id'"
              no-data-text="Sin datos para mostrar."
            >
              <template #item.tickets_vendidos="{ value }">
                {{ formatNumber(value) }}
              </template>
              <template #item.tickets_validados="{ value }">
                {{ formatNumber(value) }}
              </template>
              <template #item.tickets_sobreventa="{ value }">
                {{ formatNumber(value) }}
              </template>
            </v-data-table>
          </v-card>
        </v-col>
      </v-row>
  </v-container>
</template>

<style scoped>
.dashboard-chart-body {
  height: 320px;
}
</style>
