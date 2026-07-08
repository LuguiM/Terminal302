<template>
  <section class="public-page">
    <v-container class="public-page__container">
      <div class="page-heading">
        <p class="eyebrow">Consulta de ticket</p>
        <h1>Busca tu ticket</h1>
        <p>Ingresa el codigo impreso o digital para revisar la informacion publica del viaje.</p>
      </div>

      <v-form class="search-form" @submit.prevent="searchTicket">
        <v-text-field
          v-model.trim="ticketCode"
          clearable
          hide-details="auto"
          label="Codigo de ticket"
          prepend-inner-icon="mdi-ticket-confirmation-outline"
          variant="outlined"
        />
        <v-btn
          color="primary"
          size="large"
          type="submit"
          :disabled="!ticketCode"
        >
          <v-icon icon="mdi-magnify" start />
          Buscar
        </v-btn>
      </v-form>
    </v-container>
  </section>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const ticketCode = ref('')

const searchTicket = () => {
  if (!ticketCode.value) {
    return
  }

  router.push({
    name: 'ticket-detail',
    params: {
      codigo: ticketCode.value,
    },
  })
}
</script>
