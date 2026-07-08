<template>
  <section class="py-6 py-md-12">
    <v-container>
      <div class="d-grid align-center mb-8 public-subheader">
        <v-btn
          aria-label="Volver"
          icon="mdi-arrow-left"
          variant="text"
          @click="goBack"
        />
        <h1 class="text-primary text-h5 font-weight-black text-center mb-0">
          Rutas
        </h1>
        <span />
      </div>

      <v-row justify="center">
        <v-col cols="12" md="8">
          <v-text-field
            v-model.trim="search"
            clearable
            hide-details="auto"
            label="Buscar ruta"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            @keyup.enter="fetchRoutes"
            @click:clear="fetchRoutes"
          />
        </v-col>
      </v-row>

      <v-alert
        v-if="errorMessage"
        class="my-6"
        type="error"
        variant="tonal"
      >
        {{ errorMessage }}
      </v-alert>

      <v-skeleton-loader
        v-if="loading"
        class="mt-8"
        type="card, card, card"
      />

      <v-row v-else-if="routes.length" class="mt-4" justify="center">
        <v-col
          v-for="route in routes"
          :key="route.id"
          cols="12"
          md="6"
          lg="4"
        >
          <v-card
            :to="{ name: 'route-schedules', params: { id: route.id } }"
            class="h-100 pa-7 text-center route-card"
            elevation="8"
            rounded="lg"
            variant="outlined"
          >
            <v-card-title class="text-primary text-h5 font-weight-black justify-center">
              Ruta : {{ route.ruta }}
            </v-card-title>
            <v-card-text class="text-primary text-h6 font-weight-bold">
              {{ route.denominacion }}
              <div class="text-secondary text-body-2 mt-2">
                Tarifa ${{ route.tarifa }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

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
import { useRouter } from 'vue-router'

import { getApiErrorMessage } from '@/services/api'
import { getPublicRoutes } from '@/services/publicRouteService'

const router = useRouter()
const loading = ref(true)
const routes = ref([])
const search = ref('')
const errorMessage = ref('')

const goBack = () => {
  router.push({ name: 'home' })
}

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
