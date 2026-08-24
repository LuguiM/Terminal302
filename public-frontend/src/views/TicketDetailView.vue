<template>
  <section class="py-6 py-md-12">
    <v-container class="public-medium">
      <div class="d-grid align-center mb-8 public-subheader">
        <v-btn
          aria-label="Volver"
          icon="mdi-arrow-left"
          variant="text"
          @click="goBack"
        />
        <h1 class="text-primary text-h5 font-weight-black text-center mb-0">
          Resultado
        </h1>
        <span />
      </div>

      <v-alert
        v-if="errorMessage"
        class="mb-6"
        type="error"
        variant="tonal"
      >
        {{ errorMessage }}
      </v-alert>

      <v-skeleton-loader
        v-if="loading"
        type="article"
      />

      <v-card
        v-else-if="ticket"
        class="pa-5 pa-sm-7"
        elevation="8"
        rounded="lg"
        variant="outlined"
      >
        <div class="d-flex align-start justify-space-between ga-4 flex-wrap mb-6 pb-6 border-b">
          <div>
            <div class="text-secondary text-caption font-weight-black text-uppercase">
              Codigo
            </div>
            <h2 class="text-primary text-h4 font-weight-black">
              {{ ticket.codigo_ticket }}
            </h2>
          </div>
          <v-chip
            :color="ticket.verification?.usable ? 'success' : 'error'"
            variant="tonal"
          >
            {{ ticket.verification?.usable ? 'Utilizable' : 'No utilizable' }}
          </v-chip>
        </div>

        <v-alert
          v-if="ticket.verification"
          class="mb-6"
          :type="ticket.verification.usable ? 'success' : 'warning'"
          variant="tonal"
        >
          {{ ticket.verification.message }}
          <div
            v-if="isDevelopment"
            class="text-caption mt-1"
          >
            Origen: {{ verificationSource }}
          </div>
        </v-alert>

        <v-row>
          <v-col
            v-for="item in ticketDetails"
            :key="item.label"
            cols="12"
            sm="6"
          >
            <div class="text-secondary text-caption font-weight-black text-uppercase mb-1">
              {{ item.label }}
            </div>
            <div class="text-primary font-weight-bold">
              {{ item.value }}
            </div>
          </v-col>
        </v-row>
      </v-card>
    </v-container>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { getApiErrorMessage } from '@/services/api'
import { getPublicTicket } from '@/services/publicTicketService'

const props = defineProps({
  codigo: {
    type: String,
    required: true,
  },
})

const router = useRouter()
const loading = ref(true)
const ticket = ref(null)
const errorMessage = ref('')
const isDevelopment = import.meta.env.DEV

const verificationSource = computed(() => (
  ticket.value?.verification?.source === 'lambda'
    ? 'Lambda local'
    : 'Fallback Laravel'
))

const ticketDetails = computed(() => {
  if (!ticket.value) {
    return []
  }

  return [
    {
      label: 'Ruta',
      value: `${ticket.value.ruta} - ${ticket.value.denominacion}`,
    },
    {
      label: 'Operador',
      value: ticket.value.operador?.nombre_comercial || 'No disponible',
    },
    {
      label: 'Fecha de operacion',
      value: ticket.value.fecha_operacion || 'No disponible',
    },
    {
      label: 'Dia y hora',
      value: `${ticket.value.dia?.nombre || 'No disponible'} / ${ticket.value.hora_salida || 'No disponible'}`,
    },
    {
      label: 'Tipo de envio',
      value: ticket.value.tipo_envio?.nombre || 'No disponible',
    },
    {
      label: 'Sobreventa',
      value: ticket.value.es_sobreventa ? 'Si' : 'No',
    },
  ]
})

const goBack = () => {
  router.push({ name: 'ticket-search' })
}

onMounted(async () => {
  try {
    ticket.value = await getPublicTicket(props.codigo)
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error)
  } finally {
    loading.value = false
  }
})
</script>
