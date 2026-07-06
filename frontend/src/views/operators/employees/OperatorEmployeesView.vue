<script setup>
import { computed, onMounted, ref } from 'vue'

import AppDataTable from '@/components/common/AppDataTable.vue'
import PageTitle from '@/components/common/PageTitle.vue'
import StatusChip from '@/components/common/StatusChip.vue'
import { notify } from '@/services/notifyService'
import {
  createOperatorEmployee,
  getOperatorEmployees,
  toggleOperatorEmployeeStatus,
  updateOperatorEmployee,
} from '@/services/operatorEmployeeService'
import OperatorEmployeeFormModal from '@/views/operators/employees/components/OperatorEmployeeFormModal.vue'
import OperatorEmployeeStatusModal from '@/views/operators/employees/components/OperatorEmployeeStatusModal.vue'

const employees = ref([])
const loading = ref(false)
const error = ref(null)
const search = ref('')
const page = ref(1)
const perPage = ref(15)
const total = ref(0)
const lastPage = ref(1)
const selectedEmployee = ref(null)
const actionLoading = ref(false)
const formMode = ref('create')

const showFormModal = ref(false)
const showStatusModal = ref(false)

const employeeTableHeaders = [
  { title: 'Nombre', key: 'name', sortable: false },
  { title: 'Email', key: 'email', sortable: false },
  { title: 'Estado', key: 'estado', sortable: false, align: 'center' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

const employeeTableItems = computed(() =>
  employees.value.map((employee) => ({
    ...employee,
    estado: employee.estado?.nombre ?? '',
  })),
)

const selectedEmployeeIsActive = computed(() => isActiveStatus(selectedEmployee.value?.estado))

const getRow = (item) => item?.raw ?? item

const getEstado = (item) => getRow(item)?.estado ?? ''

const isActiveStatus = (status) => status === 'Activo'

const fetchEmployees = async () => {
  loading.value = true
  error.value = null

  try {
    const { data } = await getOperatorEmployees({
      page: page.value,
      per_page: perPage.value,
      search: search.value || undefined,
    })

    employees.value = data.empleados ?? []
    total.value = data.pagination?.total ?? 0
    lastPage.value = data.pagination?.last_page ?? 1
    page.value = data.pagination?.page ?? page.value
    perPage.value = data.pagination?.per_page ?? perPage.value
  } catch {
    error.value = 'No se pudieron cargar los empleados. Intente nuevamente.'
    employees.value = []
    total.value = 0
    lastPage.value = 1
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  page.value = 1
  fetchEmployees()
}

const handleClear = () => {
  search.value = ''
  page.value = 1
  fetchEmployees()
}

const handlePageChange = (value) => {
  page.value = value
  fetchEmployees()
}

const handlePerPageChange = (value) => {
  perPage.value = value
  page.value = 1
  fetchEmployees()
}

const closeModals = () => {
  showFormModal.value = false
  showStatusModal.value = false
  selectedEmployee.value = null
}

const openCreateModal = () => {
  selectedEmployee.value = null
  formMode.value = 'create'
  showFormModal.value = true
}

const openEditModal = (employee) => {
  selectedEmployee.value = getRow(employee)
  formMode.value = 'edit'
  showFormModal.value = true
}

const openToggleStatusModal = (employee) => {
  selectedEmployee.value = getRow(employee)
  showStatusModal.value = true
}

const handleSaveEmployee = async (payload) => {
  actionLoading.value = true

  try {
    const request = payload.id
      ? updateOperatorEmployee(payload.id, buildEmployeePayload(payload))
      : createOperatorEmployee(buildEmployeePayload(payload))

    const { data } = await request
    notify.success(data.message || 'Empleado guardado correctamente.')
    closeModals()
    await fetchEmployees()
  } finally {
    actionLoading.value = false
  }
}

const handleToggleStatus = async ({ motivo_desactivacion: motivoDesactivacion }) => {
  if (!selectedEmployee.value?.id) {
    return
  }

  actionLoading.value = true

  try {
    const payload = selectedEmployeeIsActive.value
      ? { motivo_desactivacion: motivoDesactivacion }
      : {}
    const { data } = await toggleOperatorEmployeeStatus(selectedEmployee.value.id, payload)
    notify.success(data.message || 'Estado del empleado actualizado correctamente.')
    closeModals()
    await fetchEmployees()
  } finally {
    actionLoading.value = false
  }
}

const buildEmployeePayload = (payload) => ({
  name: payload.name,
  email: payload.email,
})

onMounted(fetchEmployees)
</script>

<template>
  <v-container class="operator-employees-view" fluid>
    <PageTitle title="Empleados" />

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
          Nuevo empleado
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
      :headers="employeeTableHeaders"
      :items="employeeTableItems"
      :last-page="lastPage"
      :loading="loading"
      no-data-text="No hay empleados para mostrar."
      :page="page"
      :per-page="perPage"
      :total="total"
      @update:page="handlePageChange"
      @update:per-page="handlePerPageChange"
    >
      <template #item.email="{ value }">
        <span class="operator-employees-view__email">{{ value }}</span>
      </template>

      <template #item.estado="{ value }">
        <StatusChip :status="value" />
      </template>

      <template #item.actions="{ item }">
        <div class="employee-table__actions">
          <v-tooltip text="Editar">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Editar empleado"
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
                    ? 'Desactivar empleado'
                    : 'Activar empleado'
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

    <OperatorEmployeeFormModal
      v-model="showFormModal"
      :employee="selectedEmployee"
      :loading="actionLoading"
      :mode="formMode"
      @cancel="closeModals"
      @submit="handleSaveEmployee"
    />

    <OperatorEmployeeStatusModal
      v-model="showStatusModal"
      :employee="selectedEmployee"
      :is-active="selectedEmployeeIsActive"
      :loading="actionLoading"
      @cancel="closeModals"
      @confirm="handleToggleStatus"
    />
  </v-container>
</template>

<style scoped>
.operator-employees-view {
  color: rgb(var(--v-theme-primary));
}

.operator-employees-view__email {
  overflow-wrap: anywhere;
}

.employee-table__actions {
  align-items: center;
  display: flex;
  gap: 6px;
  justify-content: center;
  white-space: nowrap;
}
</style>
