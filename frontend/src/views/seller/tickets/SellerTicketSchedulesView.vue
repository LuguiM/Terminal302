<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import PageTitle from '@/components/common/PageTitle.vue'
import { getSellerRouteSchedules } from '@/services/sellerTicketService'
import SellerScheduleCard from '@/views/seller/tickets/components/SellerScheduleCard.vue'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const error = ref('')
const routeInfo = ref(null)
const enMeta = ref(null)
const proximo = ref(null)

const rutaId = computed(() => route.params.rutaId)

const fetchSchedules = async () => {
  loading.value = true
  error.value = ''

  try {
    const { data } = await getSellerRouteSchedules(rutaId.value)

    routeInfo.value = data.ruta ?? null
    enMeta.value = data.en_meta ?? null
    proximo.value = data.proximo_a_salir ?? null
  } catch (requestError) {
    routeInfo.value = null
    enMeta.value = null
    proximo.value = null
    error.value = requestError?.response?.data?.message || 'No se pudieron cargar los horarios disponibles.'
  } finally {
    loading.value = false
  }
}

const openSummary = (schedule) => {
  router.push({
    name: 'seller-ticket-summary',
    params: { ventaHorarioId: schedule.venta_horario_id },
    query: { rutaId: rutaId.value },
  })
}

onMounted(fetchSchedules)
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

    <PageTitle title="Seleccionar Horario" />

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

    <div
      v-else
      class="seller-ticket-schedules mx-auto d-flex flex-column ga-7"
    >
      <div
        v-if="routeInfo"
        class="text-primary text-h6 font-weight-black"
      >
        Ruta
        <span class="ms-3 text-secondary">{{ routeInfo.ruta }}</span>
      </div>

      <SellerScheduleCard
        title="En meta"
        :schedule="enMeta"
        @select="openSummary"
      />

      <SellerScheduleCard
        title="Proximo a salir"
        :schedule="proximo"
        @select="openSummary"
      />
    </div>
  </v-container>
</template>

<style scoped>
.seller-ticket-schedules {
  max-width: 920px;
}
</style>
