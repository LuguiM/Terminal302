<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { getAdminScheduleRoutes } from '@/services/adminScheduleService'
import ScheduleRouteCards from '@/views/schedules/components/ScheduleRouteCards.vue'

const router = useRouter()

const routes = ref([])
const loading = ref(false)
const error = ref('')
const search = ref('')

const fetchRoutes = async () => {
  loading.value = true
  error.value = ''

  try {
    const { data } = await getAdminScheduleRoutes({
      search: search.value || undefined,
    })

    routes.value = data.rutas ?? []
  } catch {
    routes.value = []
    error.value = 'No se pudieron cargar las rutas. Intente nuevamente.'
  } finally {
    loading.value = false
  }
}

const handleClear = () => {
  search.value = ''
  fetchRoutes()
}

const openRoute = (route) => {
  router.push({
    name: 'admin-schedules-route',
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
    :routes="routes"
    title="Horarios"
    @clear="handleClear"
    @search="fetchRoutes"
    @select="openRoute"
  />
</template>
