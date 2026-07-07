<script setup>
import { computed, onMounted, ref } from 'vue'

import AppDataTable from '@/components/common/AppDataTable.vue'
import PageTitle from '@/components/common/PageTitle.vue'
import StatusChip from '@/components/common/StatusChip.vue'
import {
  getSellerSalesHistory,
  getSellerTipoEnvios,
} from '@/services/sellerTicketService'

const tickets = ref([])
const tipoEnvios = ref([])
const loading = ref(false)
const typesLoading = ref(false)
const error = ref('')
const search = ref('')
const tipoEnvioId = ref(null)
const saleDate = ref('')
const page = ref(1)
const perPage = ref(15)
const total = ref(0)
const lastPage = ref(1)

const headers = [
  { title: 'Codigo', key: 'codigo_ticket', sortable: false },
  { title: 'Tipo de venta', key: 'tipo_venta', sortable: false },
  { title: 'Ruta', key: 'ruta', sortable: false },
  { title: 'Salida', key: 'salida', sortable: false },
  { title: 'Asiento', key: 'asiento', sortable: false, align: 'center' },
  { title: 'Estado', key: 'estado_nombre', sortable: false, align: 'center' },
  { title: 'Fecha', key: 'created_at', sortable: false },
]

const tipoEnvioOptions = computed(() =>
  tipoEnvios.value.map((tipoEnvio) => ({
    title: formatTypeName(tipoEnvio.nombre),
    value: tipoEnvio.id,
  })),
)

const items = computed(() =>
  tickets.value.map((ticket) => {
    const horario = ticket.venta_horario?.horario
    const ruta = horario?.ruta

    return {
      ...ticket,
      tipo_venta: formatTypeName(ticket.tipo_envio?.nombre),
      ruta: ruta?.ruta ?? '-',
      salida: formatTime12(horario?.hora_salida),
      asiento: ticket.numero_asiento ?? '-',
      estado_nombre: ticket.estado?.nombre ?? 'Sin estado',
      created_at: formatDate(ticket.created_at),
    }
  }),
)

const getRow = (item) => item?.raw ?? item

function formatTypeName(value) {
  if (!value) {
    return '-'
  }

  return value.charAt(0).toUpperCase() + value.slice(1)
}

function formatDate(date) {
  if (!date) {
    return '-'
  }

  return new Intl.DateTimeFormat('es-SV', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(date))
}

function formatTime12(time) {
  if (!time) {
    return '-'
  }

  const [hourValue, minuteValue] = time.toString().split(':')
  const hour = Number(hourValue)
  const minute = Number(minuteValue)

  if (Number.isNaN(hour) || Number.isNaN(minute)) {
    return time
  }

  const period = hour >= 12 ? 'pm' : 'am'
  const displayHour = hour % 12 || 12

  return `${displayHour}:${minute.toString().padStart(2, '0')} ${period}`
}

const fetchTipoEnvios = async () => {
  typesLoading.value = true

  try {
    const { data } = await getSellerTipoEnvios()
    tipoEnvios.value = data.tipo_envios ?? []
  } catch {
    tipoEnvios.value = []
  } finally {
    typesLoading.value = false
  }
}

const fetchHistory = async () => {
  loading.value = true
  error.value = ''

  try {
    const { data } = await getSellerSalesHistory({
      page: page.value,
      per_page: perPage.value,
      codigo_ticket: search.value || undefined,
      tipo_envio_id: tipoEnvioId.value || undefined,
      fecha: saleDate.value || undefined,
    })

    tickets.value = data.tickets ?? []
    total.value = data.pagination?.total ?? 0
    lastPage.value = data.pagination?.last_page ?? 1
    page.value = data.pagination?.page ?? page.value
    perPage.value = data.pagination?.per_page ?? perPage.value
  } catch (requestError) {
    tickets.value = []
    total.value = 0
    lastPage.value = 1
    error.value = requestError?.response?.data?.message || 'No se pudo cargar el historial de ventas.'
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  page.value = 1
  fetchHistory()
}

const handleClear = () => {
  search.value = ''
  tipoEnvioId.value = null
  saleDate.value = ''
  page.value = 1
  fetchHistory()
}

const handlePageChange = (value) => {
  page.value = value
  fetchHistory()
}

const handlePerPageChange = (value) => {
  perPage.value = value
  page.value = 1
  fetchHistory()
}

onMounted(() => {
  fetchTipoEnvios()
  fetchHistory()
})
</script>

<template>
  <v-container fluid>
    <PageTitle title="Historial de ventas" />

    <v-row
      align="center"
      class="mb-5"
    >
      <v-col cols="12" md="3">
        <v-text-field
          v-model="search"
          density="comfortable"
          hide-details
          placeholder="Buscar codigo"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          @keyup.enter="handleSearch"
        />
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-select
          v-model="tipoEnvioId"
          clearable
          density="comfortable"
          hide-details
          :items="tipoEnvioOptions"
          label="Tipo de venta"
          :loading="typesLoading"
          variant="outlined"
        />
      </v-col>

      <v-col cols="12" sm="6" md="2">
        <v-text-field
          v-model="saleDate"
          density="comfortable"
          hide-details
          label="Fecha"
          type="date"
          variant="outlined"
        />
      </v-col>

      <v-col cols="6" sm="3" md="2">
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

      <v-col cols="6" sm="3" md="2">
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

    <v-alert
      v-if="error"
      class="mb-5"
      color="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <AppDataTable
      :headers="headers"
      :items="items"
      :last-page="lastPage"
      :loading="loading"
      :page="page"
      :per-page="perPage"
      :total="total"
      no-data-text="No hay ventas para mostrar."
      @update:page="handlePageChange"
      @update:per-page="handlePerPageChange"
    >
      <template #item.tipo_venta="{ value }">
        <StatusChip :status="value" />
      </template>

      <template #item.estado_nombre="{ item }">
        <StatusChip :status="getRow(item).estado_nombre" />
      </template>
    </AppDataTable>
  </v-container>
</template>
