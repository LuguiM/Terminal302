<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  schedule: {
    type: Object,
    default: null,
  },
})

defineEmits(['select'])

const availableSeats = computed(() => {
  const capacity = Number(props.schedule?.capacidad ?? 0)
  const sold = Number(props.schedule?.total_tickets_vendidos ?? 0)

  return Math.max(capacity - sold, 0)
})

const disabled = computed(() => !props.schedule || !props.schedule.puede_vender)

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
  <section class="seller-schedule-card">
    <h2 class="text-primary text-subtitle-1 font-weight-black mb-3">
      {{ title }}
    </h2>

    <v-alert
      v-if="!schedule"
      color="secondary"
      variant="tonal"
    >
      No hay horario disponible.
    </v-alert>

    <v-card
      v-else
      class="seller-schedule-card__content"
      color="surface"
      rounded="lg"
      variant="outlined"
    >
      <v-card-text class="d-flex flex-column flex-md-row align-md-center ga-5 pa-5">
        <v-avatar
          color="info"
          size="34"
          variant="tonal"
        >
          <v-icon icon="mdi-bus-clock" />
        </v-avatar>

        <div class="seller-schedule-card__stat">
          <span>Salida</span>
          <strong>{{ formatTime(schedule.hora_salida) }}</strong>
        </div>

        <div class="seller-schedule-card__stat">
          <span>Ultimo asiento vendido</span>
          <strong>{{ schedule.total_tickets_vendidos ?? 0 }}</strong>
        </div>

        <div class="seller-schedule-card__stat">
          <span>Asientos disponibles</span>
          <strong>{{ availableSeats }}</strong>
        </div>

        <v-chip
          v-if="schedule.sobreventa_permitida"
          color="warning"
          variant="tonal"
        >
          Sobreventa
        </v-chip>

        <v-spacer />

        <v-btn
          color="primary"
          :disabled="disabled"
          prepend-icon="mdi-ticket-confirmation-outline"
          rounded="lg"
          variant="flat"
          @click="$emit('select', schedule)"
        >
          Boleto
        </v-btn>
      </v-card-text>
    </v-card>
  </section>
</template>

<style scoped>
.seller-schedule-card__content {
  border-color: rgb(var(--v-theme-blueLigth)) !important;
  border-width: 2px;
}

.seller-schedule-card__stat {
  color: rgb(var(--v-theme-secondary));
  display: flex;
  flex-direction: column;
  min-width: 130px;
}

.seller-schedule-card__stat span {
  font-size: 0.78rem;
  font-weight: 700;
  line-height: 1.1;
}

.seller-schedule-card__stat strong {
  color: rgb(var(--v-theme-primary));
  font-size: 1.05rem;
  font-weight: 900;
}
</style>
