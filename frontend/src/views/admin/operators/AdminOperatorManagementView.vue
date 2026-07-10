<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import AppDataTable from '@/components/common/AppDataTable.vue'
import PageTitle from '@/components/common/PageTitle.vue'
import StatusChip from '@/components/common/StatusChip.vue'
import { notify } from '@/services/notifyService'
import {
  getAdminOperators,
  toggleAdminOperatorStatus,
} from '@/services/adminOperatorService'
import AdminOperatorStatusModal from '@/views/admin/operators/components/AdminOperatorStatusModal.vue'

const router = useRouter()

const operators = ref([])
const loading = ref(false)
const error = ref(null)
const search = ref('')
const page = ref(1)
const perPage = ref(15)
const total = ref(0)
const lastPage = ref(1)
const selectedOperator = ref(null)
const actionLoading = ref(false)
const showStatusModal = ref(false)

const operatorTableHeaders = [
  { title: 'Empresa', key: 'empresa', sortable: false },
  { title: 'Rutas', key: 'rutasCount', sortable: false, align: 'center' },
  { title: 'Vehiculos', key: 'busesCount', sortable: false, align: 'center' },
  { title: 'Estado', key: 'estado', sortable: false, align: 'center' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

const operatorTableItems = computed(() =>
  operators.value.map((operator) => ({
    ...operator,
    empresa: operator.nombre_comercial || operator.razon_social || '-',
    estado: operator.estado?.nombre ?? '',
    rutasCount: operator.rutas_count ?? 0,
    busesCount: operator.buses_count ?? 0,
  })),
)

const getRow = (item) => item?.raw ?? item

const getEstado = (item) => getRow(item)?.estado ?? ''

const isActiveStatus = (status) => status === 'Activo'

const selectedOperatorIsActive = computed(() => isActiveStatus(selectedOperator.value?.estado))

const fetchOperators = async () => {
  loading.value = true
  error.value = null

  try {
    const { data } = await getAdminOperators({
      page: page.value,
      per_page: perPage.value,
      search: search.value || undefined,
    })

    operators.value = data.operadores ?? []
    total.value = data.pagination?.total ?? 0
    lastPage.value = data.pagination?.last_page ?? 1
    page.value = data.pagination?.page ?? page.value
    perPage.value = data.pagination?.per_page ?? perPage.value
  } catch {
    error.value = 'No se pudieron cargar los operadores. Intente nuevamente.'
    operators.value = []
    total.value = 0
    lastPage.value = 1
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  page.value = 1
  fetchOperators()
}

const handleClear = () => {
  search.value = ''
  page.value = 1
  fetchOperators()
}

const handlePageChange = (value) => {
  page.value = value
  fetchOperators()
}

const handlePerPageChange = (value) => {
  perPage.value = value
  page.value = 1
  fetchOperators()
}

const closeModals = () => {
  showStatusModal.value = false
  selectedOperator.value = null
}

const openToggleStatusModal = (operator) => {
  selectedOperator.value = getRow(operator)
  showStatusModal.value = true
}

const openDetail = (operator) => {
  const row = getRow(operator)

  router.push({
    name: 'admin-operator-detail',
    params: { id: row.id },
  })
}

const handleToggleStatus = async ({ motivo_desactivacion: motivoDesactivacion }) => {
  if (!selectedOperator.value?.id) {
    return
  }

  actionLoading.value = true

  try {
    const payload = selectedOperatorIsActive.value
      ? { motivo_desactivacion: motivoDesactivacion }
      : {}
    const { data } = await toggleAdminOperatorStatus(selectedOperator.value.id, payload)
    notify.success(data.message || 'Estado del operador actualizado correctamente.')
    closeModals()
    await fetchOperators()
  } finally {
    actionLoading.value = false
  }
}

onMounted(fetchOperators)
</script>

<template>
  <v-container class="admin-operators-view" fluid>
    <PageTitle title="Gestion de operadores" />

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
      :headers="operatorTableHeaders"
      :items="operatorTableItems"
      :last-page="lastPage"
      :loading="loading"
      no-data-text="No hay operadores para mostrar."
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
        <div class="operator-table__actions">
          <v-tooltip text="Ver detalles">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Ver detalles del operador"
                color="secondary"
                density="comfortable"
                icon="mdi-eye-outline"
                variant="text"
                @click="openDetail(item)"
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
                    ? 'Desactivar operador'
                    : 'Activar operador'
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
        </div>
      </template>
    </AppDataTable>

    <AdminOperatorStatusModal
      v-model="showStatusModal"
      :is-active="selectedOperatorIsActive"
      :loading="actionLoading"
      :operator="selectedOperator"
      @cancel="closeModals"
      @confirm="handleToggleStatus"
    />
  </v-container>
</template>

<style scoped>
.admin-operators-view {
  color: rgb(var(--v-theme-primary));
}

.operator-table__actions {
  align-items: center;
  display: flex;
  gap: 6px;
  justify-content: center;
  white-space: nowrap;
}
</style>
