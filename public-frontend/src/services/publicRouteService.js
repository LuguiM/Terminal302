import api from '@/services/api'

export const getPublicRoutes = async ({ search = '', page = 1, perPage = 12 } = {}) => {
  const response = await api.get('/public/rutas', {
    params: {
      search: search || undefined,
      page,
      per_page: perPage,
    },
  })

  return response.data
}

export const getPublicRouteSchedules = async (routeId) => {
  const response = await api.get(`/public/rutas/${routeId}/horarios`)

  return response.data
}
