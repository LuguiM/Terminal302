<template>
  <section class="public-page">
    <v-container class="public-page__container">
      <div class="page-heading">
        <p class="eyebrow">Resultado de consulta</p>
        <h1>Ticket {{ codigo }}</h1>
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

      <article v-else-if="ticket" class="detail-panel">
        <div class="detail-panel__header">
          <div>
            <span class="detail-label">Codigo</span>
            <h2>{{ ticket.codigo_ticket }}</h2>
          </div>
          <v-chip color="success" variant="tonal">
            {{ ticket.estado?.nombre || 'Consultado' }}
          </v-chip>
        </div>

        <dl class="detail-grid">
          <div>
            <dt>Ruta</dt>
            <dd>{{ ticket.ruta }} - {{ ticket.denominacion }}</dd>
          </div>
          <div>
            <dt>Operador</dt>
            <dd>{{ ticket.operador?.nombre_comercial || 'No disponible' }}</dd>
          </div>
          <div>
            <dt>Fecha de operacion</dt>
            <dd>{{ ticket.fecha_operacion || 'No disponible' }}</dd>
          </div>
          <div>
            <dt>Dia y hora</dt>
            <dd>{{ ticket.dia?.nombre || 'No disponible' }} · {{ ticket.hora_salida || 'No disponible' }}</dd>
          </div>
          <div>
            <dt>Tipo de envio</dt>
            <dd>{{ ticket.tipo_envio?.nombre || 'No disponible' }}</dd>
          </div>
          <div>
            <dt>Sobreventa</dt>
            <dd>{{ ticket.es_sobreventa ? 'Si' : 'No' }}</dd>
          </div>
        </dl>
      </article>
    </v-container>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'

import { getApiErrorMessage } from '@/services/api'
import { getPublicTicket } from '@/services/publicTicketService'

const props = defineProps({
  codigo: {
    type: String,
    required: true,
  },
})

const loading = ref(true)
const ticket = ref(null)
const errorMessage = ref('')

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
