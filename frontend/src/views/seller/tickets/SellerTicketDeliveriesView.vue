<script setup>
import { computed, onMounted, ref } from 'vue'

import AppDataTable from '@/components/common/AppDataTable.vue'
import PageTitle from '@/components/common/PageTitle.vue'
import StatusChip from '@/components/common/StatusChip.vue'
import { notify } from '@/services/notifyService'
import {
  getSellerDeliveries,
  retrySellerTicketDelivery,
} from '@/services/sellerTicketService'
import SellerPrintTicketsModal from './components/SellerPrintTicketsModal.vue'

const tickets = ref([])
const loading = ref(false)
const error = ref('')
const search = ref('')
const processingStatus = ref(null)
const page = ref(1)
const perPage = ref(15)
const total = ref(0)
const lastPage = ref(1)
const actionLoading = ref({})
const printTickets = ref([])
const showPrintModal = ref(false)

const statusOptions = [
  { title: 'Pendiente', value: 'pending' },
  { title: 'Procesando', value: 'processing' },
  { title: 'Completado', value: 'completed' },
  { title: 'Fallido', value: 'failed' },
]

const headers = [
  { title: 'Codigo', key: 'codigo_ticket', sortable: false },
  { title: 'Correo', key: 'correo_destino', sortable: false },
  { title: 'Telefono', key: 'telefono_destino', sortable: false },
  { title: 'Estado', key: 'procesamiento', sortable: false, align: 'center' },
  { title: 'Fecha', key: 'created_at', sortable: false },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

const items = computed(() =>
  tickets.value.map((ticket) => ({
    ...ticket,
    procesamiento: ticket.procesamiento_estado?.nombre ?? 'Sin estado',
    created_at: formatDate(ticket.created_at),
  })),
)

const formatDate = (date) => {
  if (!date) {
    return '-'
  }

  return new Intl.DateTimeFormat('es-SV', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(date))
}

const getRow = (item) => item?.raw ?? item

const isSaleOpen = (ticket) => ticket?.venta_horario?.venta_cerrada === false

const canRetryDelivery = (ticket) => {
  const processingStatusName = String(ticket?.procesamiento_estado?.nombre ?? '').toLowerCase()

  return isSaleOpen(ticket) && ['pending', 'failed'].includes(processingStatusName)
}

const setActionLoading = (ticketId, action, value) => {
  actionLoading.value = {
    ...actionLoading.value,
    [`${ticketId}-${action}`]: value,
  }
}

const isActionLoading = (ticketId, action) => actionLoading.value[`${ticketId}-${action}`] === true

const fetchDeliveries = async () => {
  loading.value = true
  error.value = ''

  try {
    const { data } = await getSellerDeliveries({
      page: page.value,
      per_page: perPage.value,
      codigo_ticket: search.value || undefined,
      processing_status_name: processingStatus.value || undefined,
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
    error.value = requestError?.response?.data?.message || 'No se pudieron cargar las entregas digitales.'
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  page.value = 1
  fetchDeliveries()
}

const handleClear = () => {
  search.value = ''
  processingStatus.value = null
  page.value = 1
  fetchDeliveries()
}

const handlePageChange = (value) => {
  page.value = value
  fetchDeliveries()
}

const handlePerPageChange = (value) => {
  perPage.value = value
  page.value = 1
  fetchDeliveries()
}

const handleRetryDelivery = async (item) => {
  const ticket = getRow(item)

  if (!ticket?.id || !canRetryDelivery(ticket)) {
    return
  }

  setActionLoading(ticket.id, 'retry', true)

  try {
    const { data } = await retrySellerTicketDelivery(ticket.id)
    const updatedTicket = data.ticket

    if (updatedTicket?.id) {
      tickets.value = tickets.value.map((currentTicket) =>
        currentTicket.id === updatedTicket.id ? updatedTicket : currentTicket,
      )
    }

    notify.success(data.message || 'Entrega reintentada correctamente.')
  } catch (requestError) {
    notify.error(requestError?.response?.data?.message || 'No se pudo reintentar la entrega digital.')
  } finally {
    setActionLoading(ticket.id, 'retry', false)
  }
}

const openPrintModal = (item) => {
  const ticket = getRow(item)

  if (!ticket?.id || !isSaleOpen(ticket)) {
    return
  }

  printTickets.value = [ticket]
  showPrintModal.value = true
}

const closePrintModal = () => {
  showPrintModal.value = false
  printTickets.value = []
}

onMounted(fetchDeliveries)
</script>

<template>
  <v-container fluid>
    <v-btn
      class="mb-8"
      prepend-icon="mdi-arrow-left"
      rounded="lg"
      size="large"
      variant="outlined"
      @click="$router.push({ name: 'seller-ticket-routes' })"
    >
      Volver
    </v-btn>

    <PageTitle title="Entregas digitales" />

    <v-row
      align="center"
      class="mb-5"
    >
      <v-col cols="12" md="4">
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
          v-model="processingStatus"
          clearable
          density="comfortable"
          hide-details
          :items="statusOptions"
          label="Estado"
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
      no-data-text="No hay entregas digitales para mostrar."
      @update:page="handlePageChange"
      @update:per-page="handlePerPageChange"
    >
      <template #item.procesamiento="{ item }">
        <StatusChip :status="getRow(item).procesamiento" />
      </template>

      <template #item.actions="{ item }">
        <div
          v-if="isSaleOpen(getRow(item))"
          class="seller-deliveries-actions"
        >
          <v-tooltip
            v-if="canRetryDelivery(getRow(item))"
            text="Reintentar entrega"
          >
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Reintentar entrega digital"
                color="secondary"
                density="comfortable"
                icon="mdi-reload"
                :loading="isActionLoading(getRow(item).id, 'retry')"
                variant="text"
                @click="handleRetryDelivery(item)"
              />
            </template>
          </v-tooltip>

          <v-tooltip text="Imprimir ticket">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Imprimir ticket"
                color="secondary"
                density="comfortable"
                icon="mdi-printer-outline"
                variant="text"
                @click="openPrintModal(item)"
              />
            </template>
          </v-tooltip>
        </div>

        <span
          v-else
          class="text-medium-emphasis"
        >
          -
        </span>
      </template>
    </AppDataTable>

    <SellerPrintTicketsModal
      v-model="showPrintModal"
      :tickets="printTickets"
      @done="closePrintModal"
    />
  </v-container>
</template>

<style scoped>
.seller-deliveries-actions {
  align-items: center;
  display: flex;
  gap: 6px;
  justify-content: center;
}
</style>
