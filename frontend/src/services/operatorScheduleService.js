import api from './api'

export const getOperatorScheduleRoutes = (params = {}) => {
  return api.get('/operador/horarios/rutas', { params })
}

export const getOperatorScheduleRouteDays = (routeId) => {
  return api.get(`/operador/horarios/rutas/${routeId}`)
}

export const getOperatorSchedules = (params = {}) => {
  return api.get('/operador/horarios', { params })
}
