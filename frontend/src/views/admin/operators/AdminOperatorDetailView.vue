<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import AppDataTable from '@/components/common/AppDataTable.vue'
import PageTitle from '@/components/common/PageTitle.vue'
import StatusChip from '@/components/common/StatusChip.vue'
import {
  getAdminOperator,
  getAdminOperatorBuses,
  getAdminOperatorEmployees,
  getAdminOperatorRoutes,
} from '@/services/adminOperatorService'

const route = useRoute()
const router = useRouter()

const operator = ref(null)
const operatorLoading = ref(false)
const operatorError = ref(null)
const activePanels = ref([])

const createSectionState = () => ({
  items: [],
  loading: false,
  error: null,
  page: 1,
  perPage: 10,
  total: 0,
  lastPage: 1,
  loaded: false,
})

const sections = reactive({
  employees: createSectionState(),
  buses: createSectionState(),
  routes: createSectionState(),
})

const sectionRequests = {
  employees: getAdminOperatorEmployees,
  buses: getAdminOperatorBuses,
  routes: getAdminOperatorRoutes,
}

const sectionDataKeys = {
  employees: 'empleados',
  buses: 'buses',
  routes: 'operador_rutas',
}

const sectionErrorMessages = {
  employees: 'No se pudieron cargar los empleados. Intente nuevamente.',
  buses: 'No se pudieron cargar las unidades de transporte. Intente nuevamente.',
  routes: 'No se pudieron cargar las rutas. Intente nuevamente.',
}

const employeeHeaders = [
  { title: 'Nombre', key: 'name', sortable: false },
  { title: 'Email', key: 'email', sortable: false },
  { title: 'Estado', key: 'estado', sortable: false, align: 'center' },
]

const busHeaders = [
  { title: 'Marca', key: 'marca', sortable: false },
  { title: 'Unidad', key: 'nombre_unidad', sortable: false },
  { title: 'Tipo de servicio', key: 'tipoServicio', sortable: false },
  { title: 'Placa', key: 'placa', sortable: false },
  { title: 'Capacidad', key: 'capacidad', sortable: false },
  { title: 'Ruta', key: 'rutaLabel', sortable: false },
  { title: 'Estado', key: 'estado', sortable: false, align: 'center' },
]

const routeHeaders = [
  { title: 'Ruta', key: 'ruta', sortable: false },
  { title: 'Denominacion', key: 'denominacion', sortable: false },
  { title: 'Tarifa', key: 'tarifaFormatted', sortable: false },
  { title: 'Estado', key: 'estado', sortable: false, align: 'center' },
]

const operatorId = computed(() => route.params.id)
const companyName = computed(() => operator.value?.nombre_comercial || operator.value?.razon_social || '-')
const responsibleName = computed(() => operator.value?.representante_legal || operator.value?.user?.name || '-')
const documentValue = computed(() => operator.value?.nit || operator.value?.dui || '-')
const phoneValue = computed(() => operator.value?.telefono || operator.value?.telefono_opcional || '-')

const employeeItems = computed(() =>
  sections.employees.items.map((employee) => ({
    ...employee,
    estado: employee.estado?.nombre ?? '',
  })),
)

const busItems = computed(() =>
  sections.buses.items.map((bus) => ({
    ...bus,
    estado: bus.estado?.nombre ?? '',
    tipoServicio: bus.tipo_bus?.nombre ?? '-',
    rutaLabel: bus.ruta?.ruta
      ? `${bus.ruta.ruta} - ${bus.ruta.denominacion ?? ''}`.trim()
      : '-',
  })),
)

const routeItems = computed(() =>
  sections.routes.items.map((operatorRoute) => ({
    ...operatorRoute,
    estado: operatorRoute.estado?.nombre ?? '',
    tarifaFormatted: formatCurrency(operatorRoute.tarifa),
  })),
)

const fetchOperator = async () => {
  operatorLoading.value = true
  operatorError.value = null

  try {
    const { data } = await getAdminOperator(operatorId.value)
    operator.value = data.data ?? null
  } catch {
    operator.value = null
    operatorError.value = 'No se pudo cargar el operador. Intente nuevamente.'
  } finally {
    operatorLoading.value = false
  }
}

const fetchSection = async (key) => {
  const section = sections[key]
  section.loading = true
  section.error = null

  try {
    const { data } = await sectionRequests[key](operatorId.value, {
      page: section.page,
      per_page: section.perPage,
    })

    section.items = data[sectionDataKeys[key]] ?? []
    section.total = data.pagination?.total ?? 0
    section.lastPage = data.pagination?.last_page ?? 1
    section.page = data.pagination?.page ?? section.page
    section.perPage = data.pagination?.per_page ?? section.perPage
    section.loaded = true
  } catch {
    section.items = []
    section.total = 0
    section.lastPage = 1
    section.error = sectionErrorMessages[key]
  } finally {
    section.loading = false
  }
}

const loadSectionIfNeeded = (key) => {
  const section = sections[key]

  if (!section.loaded && !section.loading) {
    fetchSection(key)
  }
}

const handlePanelUpdate = (value) => {
  const values = Array.isArray(value)
    ? value
    : value
      ? [value]
      : []

  values.forEach((panelKey) => {
    if (sections[panelKey]) {
      loadSectionIfNeeded(panelKey)
    }
  })
}

const handleSectionPageChange = (key, value) => {
  sections[key].page = value
  fetchSection(key)
}

const handleSectionPerPageChange = (key, value) => {
  sections[key].perPage = value
  sections[key].page = 1
  fetchSection(key)
}

const goBack = () => {
  router.push({ name: 'admin-operators' })
}

const formatCurrency = (value) => {
  const amount = Number(value ?? 0)

  return `$${amount.toFixed(2)}`
}

onMounted(fetchOperator)
</script>

<template>
  <v-container class="admin-operator-detail-view" fluid>
    <v-btn
      class="mb-6 px-8"
      prepend-icon="mdi-arrow-left"
      rounded="lg"
      variant="outlined"
      @click="goBack"
    >
      Volver
    </v-btn>

    <PageTitle title="Detalles del operador" />

    <v-progress-linear
      v-if="operatorLoading"
      class="mt-6"
      color="primary"
      indeterminate
    />

    <v-alert
      v-if="operatorError"
      class="mt-6"
      color="error"
      type="error"
      variant="tonal"
    >
      {{ operatorError }}
    </v-alert>

    <template v-if="operator">
      <v-row class="mt-10 mb-8" dense>
        <v-col cols="12" md="3">
          <div class="text-secondary font-weight-black mb-2">
            Nombre de empresa
          </div>
          <div class="text-body-1 text-medium-emphasis">
            {{ companyName }}
          </div>
        </v-col>

        <v-col cols="12" md="3">
          <div class="text-secondary font-weight-black mb-2">
            Nombre del responsable
          </div>
          <div class="text-body-1 text-medium-emphasis">
            {{ responsibleName }}
          </div>
        </v-col>

        <v-col cols="12" md="3">
          <div class="text-secondary font-weight-black mb-2">
            DUI o NIT
          </div>
          <div class="text-body-1 text-medium-emphasis">
            {{ documentValue }}
          </div>
        </v-col>

        <v-col cols="12" md="3">
          <div class="text-secondary font-weight-black mb-2">
            Telefono
          </div>
          <div class="text-body-1 text-medium-emphasis">
            {{ phoneValue }}
          </div>
        </v-col>
      </v-row>

      <v-expansion-panels
        v-model="activePanels"
        class="d-flex flex-column ga-5" 
        variant="accordion"
        @update:model-value="handlePanelUpdate"
      >
        <v-expansion-panel
          rounded="lg"
          value="routes"
        >
          <v-expansion-panel-title class="text-primary font-weight-black text-h5">
            Rutas de transporte
          </v-expansion-panel-title>

          <v-expansion-panel-text>
            <v-alert
              v-if="sections.routes.error"
              class="mb-4"
              color="error"
              type="error"
              variant="tonal"
            >
              {{ sections.routes.error }}
            </v-alert>

            <AppDataTable
              :headers="routeHeaders"
              :items="routeItems"
              :last-page="sections.routes.lastPage"
              :loading="sections.routes.loading"
              no-data-text="No hay rutas para mostrar."
              :page="sections.routes.page"
              :per-page="sections.routes.perPage"
              :total="sections.routes.total"
              @update:page="handleSectionPageChange('routes', $event)"
              @update:per-page="handleSectionPerPageChange('routes', $event)"
            >
              <template #item.estado="{ value }">
                <StatusChip :status="value" />
              </template>
            </AppDataTable>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel
          rounded="lg"
          value="buses"
        >
          <v-expansion-panel-title class="text-primary font-weight-black text-h5">
            Unidades de transporte
          </v-expansion-panel-title>

          <v-expansion-panel-text>
            <v-alert
              v-if="sections.buses.error"
              class="mb-4"
              color="error"
              type="error"
              variant="tonal"
            >
              {{ sections.buses.error }}
            </v-alert>

            <AppDataTable
              :headers="busHeaders"
              :items="busItems"
              :last-page="sections.buses.lastPage"
              :loading="sections.buses.loading"
              no-data-text="No hay unidades para mostrar."
              :page="sections.buses.page"
              :per-page="sections.buses.perPage"
              :total="sections.buses.total"
              @update:page="handleSectionPageChange('buses', $event)"
              @update:per-page="handleSectionPerPageChange('buses', $event)"
            >
              <template #item.estado="{ value }">
                <StatusChip :status="value" />
              </template>
            </AppDataTable>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel
          rounded="lg"
          value="employees"
        >
          <v-expansion-panel-title class="text-primary font-weight-black text-h5">
            Empleados
          </v-expansion-panel-title>

          <v-expansion-panel-text>
            <v-alert
              v-if="sections.employees.error"
              class="mb-4"
              color="error"
              type="error"
              variant="tonal"
            >
              {{ sections.employees.error }}
            </v-alert>

            <AppDataTable
              :headers="employeeHeaders"
              :items="employeeItems"
              :last-page="sections.employees.lastPage"
              :loading="sections.employees.loading"
              no-data-text="No hay empleados para mostrar."
              :page="sections.employees.page"
              :per-page="sections.employees.perPage"
              :total="sections.employees.total"
              @update:page="handleSectionPageChange('employees', $event)"
              @update:per-page="handleSectionPerPageChange('employees', $event)"
            >
              <template #item.estado="{ value }">
                <StatusChip :status="value" />
              </template>
            </AppDataTable>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </template>
  </v-container>
</template>

<style scoped>
.admin-operator-detail-view {
  color: rgb(var(--v-theme-primary));
}
</style>
