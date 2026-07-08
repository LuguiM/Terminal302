<template>
  <section class="public-page">
    <v-container class="public-page__container public-page__container--wide">
      <div class="page-heading">
        <p class="eyebrow">Rutas publicas</p>
        <h1>Rutas disponibles</h1>
        <p>Consulta las rutas activas que tienen horarios disponibles para los usuarios.</p>
      </div>

      <div class="search-form search-form--compact">
        <v-text-field
          v-model.trim="search"
          clearable
          hide-details="auto"
          label="Buscar por ruta o denominacion"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          @keyup.enter="fetchRoutes"
        />
        <v-btn color="primary" size="large" @click="fetchRoutes">
          Buscar
        </v-btn>
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
        type="table"
      />

      <div v-else-if="routes.length" class="route-list">
        <article
          v-for="route in routes"
          :key="route.id"
          class="route-card"
        >
          <div>
            <span class="detail-label">Ruta</span>
            <h2>{{ route.ruta }}</h2>
            <p>{{ route.denominacion }}</p>
          </div>
          <div class="route-card__meta">
            <strong>${{ route.tarifa }}</strong>
            <v-btn
              :to="{ name: 'route-schedules', params: { id: route.id } }"
              color="primary"
              variant="outlined"
            >
              <v-icon icon="mdi-clock-outline" start />
              Horarios
            </v-btn>
          </div>
        </article>
      </div>

      <v-empty-state
        v-else
        headline="Sin rutas disponibles"
        icon="mdi-map-search-outline"
        text="No se encontraron rutas activas con los filtros seleccionados."
      />
    </v-container>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'

import { getApiErrorMessage } from '@/services/api'
import { getPublicRoutes } from '@/services/publicRouteService'

const loading = ref(true)
const routes = ref([])
const search = ref('')
const errorMessage = ref('')

const fetchRoutes = async () => {
  loading.value = true
  errorMessage.value = ''

  try {
    const response = await getPublicRoutes({ search: search.value })

    routes.value = response.rutas || []
  } catch (error) {
    errorMessage.value = getApiErrorMessage(error)
    routes.value = []
  } finally {
    loading.value = false
  }
}

onMounted(fetchRoutes)
</script>
