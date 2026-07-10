<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { getSellerTicketRoutes } from '@/services/sellerTicketService'
import ScheduleRouteCards from '@/views/schedules/components/ScheduleRouteCards.vue'

const router = useRouter()

const routes = ref([])
const loading = ref(false)
const error = ref('')
const search = ref('')
const appliedSearch = ref('')

const filteredRoutes = computed(() => {
  const term = appliedSearch.value.trim().toLowerCase()

  if (!term) {
    return routes.value
  }

  return routes.value.filter((route) =>
    `${route.ruta ?? ''} ${route.denominacion ?? ''}`.toLowerCase().includes(term),
  )
})

const fetchRoutes = async () => {
  loading.value = true
  error.value = ''

  try {
    const { data } = await getSellerTicketRoutes()

    routes.value = data.rutas ?? []
  } catch {
    routes.value = []
    error.value = 'No se pudieron cargar las rutas disponibles para venta.'
  } finally {
    loading.value = false
  }
}

const applySearch = () => {
  appliedSearch.value = search.value
}

const clearSearch = () => {
  search.value = ''
  appliedSearch.value = ''
}

const openRoute = (route) => {
  router.push({
    name: 'seller-ticket-schedules',
    params: { rutaId: route.id },
  })
}

onMounted(fetchRoutes)
</script>

<template>
  <ScheduleRouteCards
    v-model:search="search"
    :error="error"
    :loading="loading"
    :routes="filteredRoutes"
    title="Seleccionar ruta a vender"
    @clear="clearSearch"
    @search="applySearch"
    @select="openRoute"
  />
</template>
