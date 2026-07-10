<script setup>
import PageTitle from "@/components/common/PageTitle.vue";

defineProps({
  title: {
    type: String,
    default: "Horarios",
  },
  routes: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: "",
  },
  search: {
    type: String,
    default: "",
  },
});

defineEmits(["update:search", "search", "clear", "select"]);
</script>

<template>
  <v-container class="schedule-route-cards" fluid>
    <PageTitle :title="title" />

    <v-row align="center" class="mt-8 mb-8">
      <v-col cols="12" md="5">
        <v-text-field
          density="comfortable"
          hide-details
          placeholder="Buscar"
          prepend-inner-icon="mdi-magnify"
          :model-value="search"
          variant="outlined"
          @keyup.enter="$emit('search')"
          @update:model-value="$emit('update:search', $event)"
        />
      </v-col>

      <v-col cols="6" sm="4" md="2">
        <v-btn
          block
          class="pa-6"
          color="primary"
          :loading="loading"
          rounded="lg"
          @click="$emit('search')"
        >
          Buscar
        </v-btn>
      </v-col>

      <v-col cols="6" sm="4" md="2">
        <v-btn
          block
          class="pa-6"
          rounded="lg"
          variant="outlined"
          @click="$emit('clear')"
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

    <v-progress-linear
      v-if="loading"
      class="mb-5"
      color="primary"
      indeterminate
    />

    <v-alert v-else-if="routes.length === 0" color="secondary" variant="tonal">
      No hay rutas para mostrar.
    </v-alert>

    <v-row v-else class="mt-2">
      <v-col
        v-for="route in routes"
        :key="route.id"
        class="d-flex"
        cols="12"
        md="4"
        sm="6"
        lg="4"
      >
        <v-card
          class="schedule-route-card d-flex flex-column align-center justify-center text-center pa-6 elevation-1"
          color="surface"
          rounded="lg"
          variant="outlined"
          @click="$emit('select', route)"
        >
          <div class="d-flex align-center">
            <v-icon
              class=""
              color="secondary"
              icon="mdi-bus-marker"
              size="36"
            />
            <div>
              <div class="text-caption text-secondary">Ruta</div>
  
              <div class="text-primary text-h4 font-weight-black">
                {{ route.ruta }}
              </div>
            </div>
          </div>

          <div class="text-caption text-secondary mt-6">Destino</div>

          <div
            class="schedule-route-card__destination text-secondary font-weight-bold"
          >
            {{ route.denominacion }}
          </div>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<style scoped>
.schedule-route-cards {
  color: rgb(var(--v-theme-primary));
}

.schedule-route-card {
  border-color: rgb(var(--v-theme-blueLigth)) !important;
  border-width: 2px;
  cursor: pointer;
  transition:
    box-shadow 0.2s ease,
    transform 0.2s ease;
  width: 100%;
}

.schedule-route-card:hover {
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
  transform: translateY(-2px);
}

.schedule-route-card__destination {
  overflow-wrap: anywhere;
  white-space: normal;
}
</style>
