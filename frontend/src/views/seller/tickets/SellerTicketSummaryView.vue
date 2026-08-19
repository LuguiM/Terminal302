<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import PageTitle from '@/components/common/PageTitle.vue'
import { notify } from '@/services/notifyService'
import {
  closeSellerSale,
  createSellerTickets,
  getSellerRouteSchedules,
  getSellerTipoEnvios,
} from '@/services/sellerTicketService'
import SellerCloseSaleModal from '@/views/seller/tickets/components/SellerCloseSaleModal.vue'
import SellerDigitalDeliveryModal from '@/views/seller/tickets/components/SellerDigitalDeliveryModal.vue'
import SellerPrintTicketsModal from '@/views/seller/tickets/components/SellerPrintTicketsModal.vue'
import SellerSaleConfirmationModal from '@/views/seller/tickets/components/SellerSaleConfirmationModal.vue'
import SellerTicketSummaryCard from '@/views/seller/tickets/components/SellerTicketSummaryCard.vue'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const actionLoading = ref(false)
const error = ref('')
const routeInfo = ref(null)
const selectedSchedule = ref(null)
const tipoEnvios = ref([])
const cantidad = ref(1)
const showDigitalModal = ref(false)
const showPrintModal = ref(false)
const showConfirmationModal = ref(false)
const showCloseSaleModal = ref(false)
const printTickets = ref([])
const generatedSummary = ref(null)

const ventaHorarioId = computed(() => Number(route.params.ventaHorarioId))
const rutaId = computed(() => route.query.rutaId)

const normalAvailableSeats = computed(() => {
  const capacity = Number(selectedSchedule.value?.capacidad ?? 0)
  const sold = Number(selectedSchedule.value?.total_tickets_vendidos ?? 0)

  return Math.max(capacity - sold, 0)
})

const capacity = computed(() => Number(selectedSchedule.value?.capacidad ?? 0))
const ticketPrice = computed(() => Number(selectedSchedule.value?.ruta?.tarifa ?? routeInfo.value?.tarifa ?? 0))
const total = computed(() => cantidad.value * ticketPrice.value)
const lowAvailability = computed(() =>
  capacity.value > 0
  && normalAvailableSeats.value > 0
  && normalAvailableSeats.value <= Math.ceil(capacity.value * 0.1),
)
const isOverbooking = computed(() =>
  selectedSchedule.value?.sobreventa_permitida
  && cantidad.value > normalAvailableSeats.value,
)
const canSell = computed(() => {
  if (!selectedSchedule.value?.puede_vender || cantidad.value <= 0) {
    return false
  }

  if (selectedSchedule.value.sobreventa_permitida) {
    return true
  }

  return normalAvailableSeats.value > 0 && cantidad.value <= normalAvailableSeats.value
})
const previewTickets = computed(() =>
  Array.from({ length: Math.min(cantidad.value, 10) }, (_, index) => index),
)
const nextTicketNumber = computed(() =>
  Number(selectedSchedule.value?.total_tickets_vendidos ?? 0) + 1,
)

const printedType = computed(() =>
  tipoEnvios.value.find((type) => type.nombre?.toLowerCase() === 'impreso'),
)
const digitalType = computed(() =>
  tipoEnvios.value.find((type) => type.nombre?.toLowerCase() === 'digital'),
)

const currencyFormatter = new Intl.NumberFormat('es-SV', {
  style: 'currency',
  currency: 'USD',
})

const fetchContext = async () => {
  if (!rutaId.value) {
    error.value = 'No se pudo identificar la ruta del horario seleccionado.'
    return
  }

  loading.value = true
  error.value = ''

  try {
    const [{ data: schedulesData }, { data: typesData }] = await Promise.all([
      getSellerRouteSchedules(rutaId.value),
      getSellerTipoEnvios(),
    ])

    routeInfo.value = schedulesData.ruta ?? null
    tipoEnvios.value = typesData.tipo_envios ?? []
    selectedSchedule.value = [schedulesData.en_meta, schedulesData.proximo_a_salir]
      .filter(Boolean)
      .find((schedule) => Number(schedule.venta_horario_id) === ventaHorarioId.value) ?? null

    if (!selectedSchedule.value) {
      error.value = 'El horario seleccionado ya no esta disponible.'
    }
  } catch (requestError) {
    error.value = requestError?.response?.data?.message || 'No se pudo cargar el resumen del ticket.'
  } finally {
    loading.value = false
  }
}

const increaseQuantity = () => {
  if (!selectedSchedule.value?.sobreventa_permitida && cantidad.value >= normalAvailableSeats.value) {
    return
  }

  cantidad.value += 1
}

const decreaseQuantity = () => {
  cantidad.value = Math.max(cantidad.value - 1, 1)
}

const buildPayload = (tipoEnvio, delivery = {}) => ({
  venta_horario_id: ventaHorarioId.value,
  cantidad: cantidad.value,
  tipo_envio_id: tipoEnvio.id,
  ...delivery,
})

const handlePrintedSale = async () => {
  if (!printedType.value || !canSell.value) {
    return
  }

  actionLoading.value = true

  try {
    const { data } = await createSellerTickets(buildPayload(printedType.value))

    notify.success(data.message || 'Tickets generados correctamente.')
    generatedSummary.value = data.resumen ?? null
    printTickets.value = data.impresion?.tickets ?? []
    showPrintModal.value = true
    await fetchContext()
  } catch (requestError) {
    notify.error(requestError?.response?.data?.message || 'No se pudo generar la venta impresa.')
  } finally {
    actionLoading.value = false
  }
}

const handleDigitalSale = async (delivery) => {
  if (!digitalType.value || !canSell.value) {
    return
  }

  actionLoading.value = true

  try {
    const { data } = await createSellerTickets(buildPayload(digitalType.value, delivery))

    notify.success(data.message || 'Tickets digitales generados correctamente.')
    generatedSummary.value = data.resumen ?? null
    showDigitalModal.value = false
    showConfirmationModal.value = true
    await fetchContext()
  } catch (requestError) {
    notify.error(requestError?.response?.data?.message || 'No se pudo generar la venta digital.')
  } finally {
    actionLoading.value = false
  }
}

const handleCloseSale = async (payload) => {
  actionLoading.value = true

  try {
    const { data } = await closeSellerSale(ventaHorarioId.value, payload)

    notify.success(data.message || 'Venta cerrada correctamente.')
    showCloseSaleModal.value = false
    await fetchContext()
  } catch (requestError) {
    notify.error(requestError?.response?.data?.message || 'No se pudo cerrar la venta.')
  } finally {
    actionLoading.value = false
  }
}

const handlePrintDone = () => {
  showPrintModal.value = false
  showConfirmationModal.value = true
}

const goToSameSchedule = () => {
  showConfirmationModal.value = false
  cantidad.value = 1
  fetchContext()
}

const goToSchedules = () => {
  router.push({
    name: 'seller-ticket-schedules',
    params: { rutaId: rutaId.value },
  })
}

const goToRoutes = () => {
  router.push({ name: 'seller-ticket-routes' })
}

const goToDeliveries = () => {
  router.push({ name: 'seller-ticket-deliveries' })
}

onMounted(fetchContext)
</script>

<template>
  <v-container fluid>
    <v-btn
      class="mb-8"
      prepend-icon="mdi-arrow-left"
      rounded="lg"
      size="large"
      variant="outlined"
      @click="goToSchedules"
    >
      Volver
    </v-btn>

    <PageTitle title="Resumen del ticket" />

    <v-alert
      v-if="error"
      class="mb-5"
      color="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <v-progress-linear
      v-if="loading"
      color="primary"
      indeterminate
    />

    <v-row
      v-else-if="selectedSchedule"
      class="justify-center"
    >
      <v-col cols="12" lg="8">
        <div class="d-flex flex-column ga-4">
          <SellerTicketSummaryCard
            v-for="ticketIndex in previewTickets"
            :key="ticketIndex"
            :schedule="selectedSchedule"
            :ticket-number="nextTicketNumber + ticketIndex"
          />

          <v-alert
            v-if="cantidad > previewTickets.length"
            color="secondary"
            variant="tonal"
          >
            Se generaran {{ cantidad }} tickets en total.
          </v-alert>
        </div>
      </v-col>

      <v-col cols="12" md="8" lg="3">
        <v-card
          class="pa-4"
          color="surface"
          rounded="lg"
          variant="outlined"
        >
          <div class="text-center text-primary font-weight-black mb-3">
            Cantidad de tickets
          </div>

          <div class="d-flex align-center justify-center ga-4 mb-4">
            <v-btn
              aria-label="Disminuir cantidad"
              color="secondary"
              density="comfortable"
              icon="mdi-minus-circle-outline"
              variant="text"
              @click="decreaseQuantity"
            />

            <div class="text-primary text-h5 font-weight-black">
              {{ cantidad }}
            </div>

            <v-btn
              aria-label="Aumentar cantidad"
              color="secondary"
              density="comfortable"
              icon="mdi-plus-circle-outline"
              variant="text"
              @click="increaseQuantity"
            />
          </div>

          <v-alert
            v-if="lowAvailability"
            class="mb-3"
            color="warning"
            density="compact"
            variant="tonal"
          >
            Quedan pocos boletos normales para este horario.
          </v-alert>

          <v-alert
            v-if="isOverbooking"
            class="mb-3"
            color="warning"
            density="compact"
            variant="tonal"
          >
            Esta venta incluira boletos con sobreventa.
          </v-alert>

          <div class="text-primary font-weight-black mb-4">
            Total a pagar:
            <span class="text-secondary">{{ currencyFormatter.format(total) }}</span>
          </div>

          <div class="d-flex flex-column ga-3">
            <v-btn
              color="primary"
              :disabled="!printedType || !canSell"
              :loading="actionLoading"
              prepend-icon="mdi-printer-outline"
              rounded="lg"
              variant="flat"
              @click="handlePrintedSale"
            >
              Imprimir
            </v-btn>

            <v-btn
              color="primary"
              :disabled="!digitalType || !canSell"
              prepend-icon="mdi-email-outline"
              rounded="lg"
              variant="flat"
              @click="showDigitalModal = true"
            >
              Enviar por correo
            </v-btn>

            <v-btn
              color="error"
              :disabled="!selectedSchedule.puede_vender"
              rounded="lg"
              variant="outlined"
              @click="showCloseSaleModal = true"
            >
              Cerrar venta
            </v-btn>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <SellerDigitalDeliveryModal
      v-model="showDigitalModal"
      :loading="actionLoading"
      @submit="handleDigitalSale"
    />

    <SellerPrintTicketsModal
      v-model="showPrintModal"
      :tickets="printTickets"
      @done="handlePrintDone"
    />

    <SellerSaleConfirmationModal
      v-model="showConfirmationModal"
      :summary="generatedSummary"
      @deliveries="goToDeliveries"
      @routes="goToRoutes"
      @same-schedule="goToSameSchedule"
      @schedules="goToSchedules"
    />

    <SellerCloseSaleModal
      v-model="showCloseSaleModal"
      :loading="actionLoading"
      @submit="handleCloseSale"
    />
  </v-container>
</template>
