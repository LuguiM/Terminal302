<script setup>
import { ref } from 'vue'

const props = defineProps({
  route: {
    type: Object,
    default: null,
  },
  days: {
    type: Array,
    default: () => [],
  },
  sections: {
    type: Object,
    default: () => ({}),
  },
  readonly: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['open-day', 'edit', 'delete'])

const activePanels = ref([])

const sectionForDay = (dayId) => props.sections[String(dayId)] ?? {
  items: [],
  loading: false,
  error: null,
  loaded: false,
}

const handlePanelUpdate = (value) => {
  const values = Array.isArray(value)
    ? value
    : value
      ? [value]
      : []

  values.forEach((dayId) => {
    emit('open-day', Number(dayId))
  })
}

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
  <div class="schedule-day-panels">
    <div class="d-flex flex-column flex-md-row align-md-center justify-md-space-between ga-4 mb-8">
      <div class="text-primary text-h5 font-weight-black">
        Ruta
        <span class="ms-3 text-secondary">
          {{ route?.ruta ?? '-' }}
        </span>
      </div>

      <slot name="actions" />
    </div>

    <v-alert
      v-if="days.length === 0"
      color="secondary"
      variant="tonal"
    >
      No hay dias con horarios para mostrar.
    </v-alert>

    <v-expansion-panels
      v-else
      v-model="activePanels"
      class="d-flex flex-column ga-5"
      multiple
      variant="accordion"
      @update:model-value="handlePanelUpdate"
    >
      <v-expansion-panel
        v-for="day in days"
        :key="day.id"
        rounded="lg"
        :value="String(day.id)"
      >
        <v-expansion-panel-title class="text-primary font-weight-black text-h5">
          {{ day.nombre }}
        </v-expansion-panel-title>

        <v-expansion-panel-text>
          <v-alert
            v-if="sectionForDay(day.id).error"
            class="mb-4"
            color="error"
            type="error"
            variant="tonal"
          >
            {{ sectionForDay(day.id).error }}
          </v-alert>

          <v-progress-linear
            v-if="sectionForDay(day.id).loading"
            color="primary"
            indeterminate
          />

          <v-alert
            v-else-if="sectionForDay(day.id).items.length === 0"
            color="secondary"
            variant="tonal"
          >
            No hay horarios para este dia.
          </v-alert>

          <v-sheet
            v-else
            border
            class="overflow-x-auto"
            rounded="lg"
          >
            <v-table class="schedule-day-table">
              <thead>
                <tr>
                  <th>Operador</th>
                  <th>Unidad</th>
                  <th>Hora de salida</th>
                  <th v-if="!readonly">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="schedule in sectionForDay(day.id).items"
                  :key="schedule.id"
                >
                  <td>{{ schedule.operador?.nombre_comercial ?? '-' }}</td>
                  <td>
                    {{ schedule.bus?.placa ?? '-' }}
                    <span v-if="schedule.bus?.nombre_unidad">
                      - {{ schedule.bus.nombre_unidad }}
                    </span>
                  </td>
                  <td>{{ formatTime(schedule.hora_salida) }}</td>
                  <td v-if="!readonly">
                    <div class="schedule-day-table__actions">
                      <v-tooltip text="Editar">
                        <template #activator="{ props: tooltipProps }">
                          <v-btn
                            v-bind="tooltipProps"
                            aria-label="Editar horario"
                            color="secondary"
                            density="comfortable"
                            icon="mdi-pencil-box-outline"
                            variant="text"
                            @click="$emit('edit', schedule)"
                          />
                        </template>
                      </v-tooltip>

                      <v-tooltip text="Eliminar">
                        <template #activator="{ props: tooltipProps }">
                          <v-btn
                            v-bind="tooltipProps"
                            aria-label="Eliminar horario"
                            color="error"
                            density="comfortable"
                            icon="mdi-trash-can-outline"
                            variant="text"
                            @click="$emit('delete', schedule)"
                          />
                        </template>
                      </v-tooltip>
                    </div>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-sheet>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<style scoped>
.schedule-day-panels {
  color: rgb(var(--v-theme-primary));
}

.schedule-day-table {
  min-width: 760px;
}

.schedule-day-table :deep(th) {
  color: rgb(var(--v-theme-primary));
  font-size: 1.05rem;
  font-weight: 900;
  text-align: center;
  white-space: nowrap;
}

.schedule-day-table :deep(td) {
  color: rgb(var(--v-theme-secondary));
  font-weight: 700;
  text-align: center;
}

.schedule-day-table__actions {
  align-items: center;
  display: flex;
  gap: 6px;
  justify-content: center;
  white-space: nowrap;
}
</style>
