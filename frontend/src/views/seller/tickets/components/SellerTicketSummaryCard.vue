<script setup>
defineProps({
  schedule: {
    type: Object,
    required: true,
  },
  ticketNumber: {
    type: Number,
    required: true,
  },
})

const formatTime = (time) => {
  if (!time) {
    return '-'
  }

  const [hour, minute] = String(time).split(':')
  const hourNumber = Number(hour)

  if (Number.isNaN(hourNumber)) {
    return time
  }

  const suffix = hourNumber >= 12 ? 'pm' : 'am'
  const displayHour = hourNumber % 12 || 12

  return `${displayHour}:${minute} ${suffix}`
}
</script>

<template>
  <v-card
    class="seller-ticket-summary-card"
    color="surface"
    rounded="lg"
    variant="outlined"
  >
    <v-card-text class="d-flex flex-column flex-md-row align-md-center ga-4 pa-4">
      <div class="seller-ticket-summary-card__brand d-flex align-center ga-2">
        <v-icon
          color="blueLigth"
          icon="mdi-bus"
          size="38"
        />
        <strong class="text-primary">TERMINAL 302</strong>
      </div>

      <div class="seller-ticket-summary-card__field">
        <span>Numero de ticket</span>
        <strong>#{{ String(ticketNumber).padStart(2, '0') }}</strong>
      </div>

      <div class="seller-ticket-summary-card__field">
        <span>Ruta</span>
        <strong>{{ schedule.ruta?.ruta ?? '-' }}</strong>
      </div>

      <div class="seller-ticket-summary-card__field">
        <span>Salida</span>
        <strong>{{ formatTime(schedule.hora_salida) }}</strong>
      </div>

      <div class="seller-ticket-summary-card__field">
        <span>Operador</span>
        <strong>{{ schedule.operador?.nombre_comercial ?? '-' }}</strong>
      </div>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.seller-ticket-summary-card {
  border-color: rgba(var(--v-theme-primary), 0.42) !important;
}

.seller-ticket-summary-card__brand {
  min-width: 150px;
}

.seller-ticket-summary-card__field {
  color: rgb(var(--v-theme-secondary));
  display: flex;
  flex-direction: column;
  min-width: 110px;
}

.seller-ticket-summary-card__field span {
  color: rgb(var(--v-theme-primary));
  font-size: 0.78rem;
  font-weight: 900;
}

.seller-ticket-summary-card__field strong {
  font-weight: 700;
}
</style>
