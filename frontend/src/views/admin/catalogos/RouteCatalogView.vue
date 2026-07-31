<script setup>
import { computed, onMounted, ref } from 'vue'

import AppDataTable from '@/components/common/AppDataTable.vue'
import PageTitle from '@/components/common/PageTitle.vue'
import StatusChip from '@/components/common/StatusChip.vue'
import { notify } from '@/services/notifyService'
import {
  createRoute,
  deleteRoute,
  getRoutes,
  toggleRouteStatus,
  updateRoute,
} from '@/services/routeService'
import RouteDeleteModal from '@/views/admin/catalogos/components/RouteDeleteModal.vue'
import RouteFormModal from '@/views/admin/catalogos/components/RouteFormModal.vue'
import RouteStatusModal from '@/views/admin/catalogos/components/RouteStatusModal.vue'

const routes = ref([])
const loading = ref(false)
const error = ref(null)
const search = ref('')
const page = ref(1)
const perPage = ref(15)
const total = ref(0)
const lastPage = ref(1)
const selectedRoute = ref(null)
const actionLoading = ref(false)

const showFormModal = ref(false)
const formMode = ref('create')
const showDeleteModal = ref(false)
const showStatusModal = ref(false)

const routeTableHeaders = [
  { title: 'Rutas', key: 'ruta', sortable: false },
  { title: 'Denominacion', key: 'denominacion', sortable: false },
  { title: 'Tarifa', key: 'tarifaFormatted', sortable: false },
  { title: 'Estado', key: 'estado', sortable: false, align: 'center' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

const routeTableItems = computed(() =>
  routes.value.map((route) => ({
    ...route,
    estado: route.estado?.nombre ?? '',
    tarifaFormatted: formatCurrency(route.tarifa),
  })),
)

const getRow = (item) => item?.raw ?? item

const getEstado = (item) => getRow(item)?.estado ?? ''

const isActiveStatus = (status) => status === 'Activo'

const selectedRouteIsActive = computed(() => isActiveStatus(selectedRoute.value?.estado))

const fetchRoutes = async () => {
  loading.value = true
  error.value = null

  try {
    const { data } = await getRoutes({
      page: page.value,
      per_page: perPage.value,
      search: search.value || undefined,
    })

    routes.value = data.rutas ?? []
    total.value = data.pagination?.total ?? 0
    lastPage.value = data.pagination?.last_page ?? 1
    page.value = data.pagination?.page ?? page.value
    perPage.value = data.pagination?.per_page ?? perPage.value
  } catch {
    error.value = 'No se pudieron cargar las rutas. Intente nuevamente.'
    routes.value = []
    total.value = 0
    lastPage.value = 1
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  page.value = 1
  fetchRoutes()
}

const handleClear = () => {
  search.value = ''
  page.value = 1
  fetchRoutes()
}

const handlePageChange = (value) => {
  page.value = value
  fetchRoutes()
}

const handlePerPageChange = (value) => {
  perPage.value = value
  page.value = 1
  fetchRoutes()
}

const closeModals = () => {
  showFormModal.value = false
  showDeleteModal.value = false
  showStatusModal.value = false
  selectedRoute.value = null
}

const openCreateModal = () => {
  selectedRoute.value = null
  formMode.value = 'create'
  showFormModal.value = true
}

const openEditModal = (route) => {
  selectedRoute.value = getRow(route)
  formMode.value = 'edit'
  showFormModal.value = true
}

const openToggleStatusModal = (route) => {
  selectedRoute.value = getRow(route)
  showStatusModal.value = true
}

const openDeleteModal = (route) => {
  selectedRoute.value = getRow(route)
  showDeleteModal.value = true
}

const handleSubmitRoute = async (payload) => {
  actionLoading.value = true

  try {
    const routeData = {
      ruta: payload.ruta,
      denominacion: payload.denominacion,
      tarifa: payload.tarifa,
    }
    const { data } = formMode.value === 'edit'
      ? await updateRoute(payload.id, routeData)
      : await createRoute(routeData)

    notify.success(
      data.message
        || (formMode.value === 'edit'
          ? 'Ruta actualizada correctamente.'
          : 'Ruta creada correctamente.'),
    )
    closeModals()
    await fetchRoutes()
  } finally {
    actionLoading.value = false
  }
}

const handleToggleStatus = async () => {
  if (!selectedRoute.value?.id) {
    return
  }

  actionLoading.value = true

  try {
    const { data } = await toggleRouteStatus(selectedRoute.value.id)
    notify.success(data.message || 'Estado de la ruta actualizado correctamente.')
    closeModals()
    await fetchRoutes()
  } finally {
    actionLoading.value = false
  }
}

const handleDeleteRoute = async () => {
  if (!selectedRoute.value?.id) {
    return
  }

  actionLoading.value = true

  try {
    const { data } = await deleteRoute(selectedRoute.value.id)
    notify.success(data.message || 'Ruta eliminada correctamente.')
    closeModals()
    await fetchRoutes()
  } finally {
    actionLoading.value = false
  }
}

const formatCurrency = (value) => {
  const amount = Number(value ?? 0)

  return `$${amount.toFixed(2)}`
}

onMounted(fetchRoutes)
</script>

<template>
  <v-container class="route-catalog-view" fluid>
    <PageTitle title="Catalogo de rutas" />

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
          Registrar ruta
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
      :headers="routeTableHeaders"
      :items="routeTableItems"
      :last-page="lastPage"
      :loading="loading"
      no-data-text="No hay rutas para mostrar."
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
        <div class="route-table__actions">
          <v-tooltip text="Editar">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Editar ruta"
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
                    ? 'Desactivar ruta'
                    : 'Activar ruta'
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

          <v-tooltip text="Eliminar">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Eliminar ruta"
                color="error"
                density="comfortable"
                icon="mdi-trash-can-outline"
                variant="text"
                @click="openDeleteModal(item)"
              />
            </template>
          </v-tooltip>
        </div>
      </template>
    </AppDataTable>

    <RouteFormModal
      v-model="showFormModal"
      :loading="actionLoading"
      :mode="formMode"
      :route="selectedRoute"
      @cancel="closeModals"
      @submit="handleSubmitRoute"
    />

    <RouteStatusModal
      v-model="showStatusModal"
      :is-active="selectedRouteIsActive"
      :loading="actionLoading"
      :route="selectedRoute"
      @cancel="closeModals"
      @confirm="handleToggleStatus"
    />

    <RouteDeleteModal
      v-model="showDeleteModal"
      :loading="actionLoading"
      :route="selectedRoute"
      @cancel="closeModals"
      @confirm="handleDeleteRoute"
    />
  </v-container>
</template>

<style scoped>
.route-catalog-view {
  color: rgb(var(--v-theme-primary));
}

.route-table__actions {
  align-items: center;
  display: flex;
  gap: 6px;
  justify-content: center;
  white-space: nowrap;
}
</style>
