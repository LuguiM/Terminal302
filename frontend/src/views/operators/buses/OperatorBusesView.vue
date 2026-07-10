<script setup>
import { computed, onMounted, ref } from 'vue'

import AppDataTable from '@/components/common/AppDataTable.vue'
import PageTitle from '@/components/common/PageTitle.vue'
import StatusChip from '@/components/common/StatusChip.vue'
import { notify } from '@/services/notifyService'
import {
  createOperatorBus,
  getOperatorBuses,
  getOperatorBusTypes,
  toggleOperatorBusStatus,
  updateOperatorBus,
} from '@/services/operatorBusService'
import { getOperatorRoutes } from '@/services/operatorRouteService'
import OperatorBusFormModal from '@/views/operators/buses/components/OperatorBusFormModal.vue'
import OperatorBusStatusModal from '@/views/operators/buses/components/OperatorBusStatusModal.vue'

const buses = ref([])
const busTypes = ref([])
const assignedRoutes = ref([])
const loading = ref(false)
const catalogsLoading = ref(false)
const error = ref(null)
const search = ref('')
const page = ref(1)
const perPage = ref(15)
const total = ref(0)
const lastPage = ref(1)
const selectedBus = ref(null)
const actionLoading = ref(false)
const formMode = ref('create')

const showFormModal = ref(false)
const showStatusModal = ref(false)

const busTableHeaders = [
  { title: 'Marca', key: 'marca', sortable: false },
  { title: 'Tipo de unidad', key: 'tipoServicio', sortable: false },
  { title: 'Capacidad', key: 'capacidadFormatted', sortable: false },
  { title: 'Estado', key: 'estado', sortable: false, align: 'center' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

const busTableItems = computed(() =>
  buses.value.map((bus) => ({
    ...bus,
    estado: bus.estado?.nombre ?? '',
    tipoServicio: formatBusTypeName(bus.tipo_bus?.nombre),
    capacidadFormatted: formatCapacity(bus.capacidad),
  })),
)

const activeAssignedRoutes = computed(() =>
  assignedRoutes.value.filter((route) => route.estado?.nombre === 'Activo'),
)

const selectedBusIsActive = computed(() => isActiveStatus(selectedBus.value?.estado))

const getRow = (item) => item?.raw ?? item

const getEstado = (item) => getRow(item)?.estado ?? ''

const isActiveStatus = (status) => status === 'Activo'

const fetchBuses = async () => {
  loading.value = true
  error.value = null

  try {
    const { data } = await getOperatorBuses({
      page: page.value,
      per_page: perPage.value,
      search: search.value || undefined,
    })

    buses.value = data.buses ?? []
    total.value = data.pagination?.total ?? 0
    lastPage.value = data.pagination?.last_page ?? 1
    page.value = data.pagination?.page ?? page.value
    perPage.value = data.pagination?.per_page ?? perPage.value
  } catch {
    error.value = 'No se pudieron cargar las unidades. Intente nuevamente.'
    buses.value = []
    total.value = 0
    lastPage.value = 1
  } finally {
    loading.value = false
  }
}

const fetchCatalogs = async () => {
  catalogsLoading.value = true

  try {
    const [busTypesResponse, routesResponse] = await Promise.all([
      getOperatorBusTypes(),
      getOperatorRoutes({ per_page: 50 }),
    ])

    busTypes.value = busTypesResponse.data.tipo_buses ?? []
    assignedRoutes.value = (routesResponse.data.operador_rutas ?? [])
      .filter((route) => route.ruta_id)
      .map((route) => ({
        ...route,
        id: route.ruta_id,
      }))
  } catch {
    busTypes.value = []
    assignedRoutes.value = []
  } finally {
    catalogsLoading.value = false
  }
}

const handleSearch = () => {
  page.value = 1
  fetchBuses()
}

const handleClear = () => {
  search.value = ''
  page.value = 1
  fetchBuses()
}

const handlePageChange = (value) => {
  page.value = value
  fetchBuses()
}

const handlePerPageChange = (value) => {
  perPage.value = value
  page.value = 1
  fetchBuses()
}

const closeModals = () => {
  showFormModal.value = false
  showStatusModal.value = false
  selectedBus.value = null
}

const openCreateModal = () => {
  selectedBus.value = null
  formMode.value = 'create'
  showFormModal.value = true
  fetchCatalogs()
}

const openEditModal = (bus) => {
  selectedBus.value = getRow(bus)
  formMode.value = 'edit'
  showFormModal.value = true
  fetchCatalogs()
}

const openToggleStatusModal = (bus) => {
  selectedBus.value = getRow(bus)
  showStatusModal.value = true
}

const handleSaveBus = async (payload) => {
  actionLoading.value = true

  try {
    const request = payload.id
      ? updateOperatorBus(payload.id, buildBusPayload(payload))
      : createOperatorBus(buildBusPayload(payload))

    const { data } = await request
    notify.success(data.message || 'Unidad guardada correctamente.')
    closeModals()
    await fetchBuses()
  } finally {
    actionLoading.value = false
  }
}

const handleToggleStatus = async () => {
  if (!selectedBus.value?.id) {
    return
  }

  actionLoading.value = true

  try {
    const { data } = await toggleOperatorBusStatus(selectedBus.value.id)
    notify.success(data.message || 'Estado de la unidad actualizado correctamente.')
    closeModals()
    await fetchBuses()
  } finally {
    actionLoading.value = false
  }
}

const buildBusPayload = (payload) => ({
  ruta_id: payload.ruta_id,
  placa: payload.placa,
  marca: payload.marca,
  nombre_unidad: payload.nombre_unidad,
  capacidad: payload.capacidad,
  tipo_bus_id: payload.tipo_bus_id,
})

const formatCapacity = (value) => {
  const amount = Number(value ?? 0)

  return `${amount} personas`
}

const formatBusTypeName = (value = '') => {
  return value
    .toString()
    .replace(/^\w/, (letter) => letter.toUpperCase())
}

onMounted(() => {
  fetchBuses()
  fetchCatalogs()
})
</script>

<template>
  <v-container class="operator-buses-view" fluid>
    <PageTitle title="Unidades de transporte" />

    <v-row align="center" class="mt-8 mb-8" justify="space-between">
      <v-col cols="12" lg="7">
        <v-row align="center">
          <v-col cols="12" md="6">
            <v-text-field
              v-model="search"
              density="comfortable"
              hide-details
              placeholder="Buscar"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              @keyup.enter="handleSearch"
            />
          </v-col>

          <v-col cols="6" sm="4" md="3">
            <v-btn
              block
              class="pa-6"
              color="primary"
              :loading="loading"
              rounded="lg"
              @click="handleSearch"
            >
              Buscar
            </v-btn>
          </v-col>

          <v-col cols="6" sm="4" md="3">
            <v-btn
              block
              class="pa-6"
              rounded="lg"
              variant="outlined"
              @click="handleClear"
            >
              Limpiar
            </v-btn>
          </v-col>
        </v-row>
      </v-col>

      <v-col cols="12" lg="3">
        <v-btn
          block
          class="pa-6"
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          @click="openCreateModal"
        >
          Registrar unidad
        </v-btn>
      </v-col>
    </v-row>

    <v-alert
      v-if="error"
      class="mb-4"
      color="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <AppDataTable
      :headers="busTableHeaders"
      :items="busTableItems"
      :last-page="lastPage"
      :loading="loading"
      no-data-text="No hay unidades para mostrar."
      :page="page"
      :per-page="perPage"
      :total="total"
      @update:page="handlePageChange"
      @update:per-page="handlePerPageChange"
    >
      <template #item.estado="{ value }">
        <StatusChip :status="value" />
      </template>

      <template #item.actions="{ item }">
        <div class="bus-table__actions">
          <v-tooltip text="Editar">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Editar unidad"
                color="secondary"
                density="comfortable"
                icon="mdi-pencil-box-outline"
                variant="text"
                @click="openEditModal(item)"
              />
            </template>
          </v-tooltip>

          <v-tooltip
            :text="isActiveStatus(getEstado(item)) ? 'Desactivar' : 'Activar'"
          >
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                :aria-label="
                  isActiveStatus(getEstado(item))
                    ? 'Desactivar unidad'
                    : 'Activar unidad'
                "
                color="secondary"
                density="comfortable"
                :icon="
                  isActiveStatus(getEstado(item))
                    ? 'mdi-close-circle-outline'
                    : 'mdi-check-circle-outline'
                "
                variant="text"
                @click="openToggleStatusModal(item)"
              />
            </template>
          </v-tooltip>

          <!-- TODO: habilitar eliminacion cuando exista DELETE /operador/buses/{id}. -->
        </div>
      </template>
    </AppDataTable>

    <OperatorBusFormModal
      v-model="showFormModal"
      :bus="selectedBus"
      :bus-types="busTypes"
      :catalogs-loading="catalogsLoading"
      :loading="actionLoading"
      :mode="formMode"
      :routes="activeAssignedRoutes"
      @cancel="closeModals"
      @submit="handleSaveBus"
    />

    <OperatorBusStatusModal
      v-model="showStatusModal"
      :bus="selectedBus"
      :is-active="selectedBusIsActive"
      :loading="actionLoading"
      @cancel="closeModals"
      @confirm="handleToggleStatus"
    />
  </v-container>
</template>

<style scoped>
.operator-buses-view {
  color: rgb(var(--v-theme-primary));
}

.bus-table__actions {
  align-items: center;
  display: flex;
  gap: 6px;
  justify-content: center;
  white-space: nowrap;
}
</style>
