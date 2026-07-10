import api from './api'

export const getOperatorRoutes = (params = {}) => {
  return api.get('/operador/rutas', { params })
}

export const getAvailableOperatorRoutes = () => {
  return api.get('/operador/rutas-disponibles')
}

export const createOperatorRoute = (payload) => {
  return api.post('/operador/rutas', payload)
}

export const toggleOperatorRouteStatus = (id) => {
  return api.patch(`/operador/rutas/${id}/toggle-status`)
}

export const deleteOperatorRoute = (id) => {
  return api.delete(`/operador/rutas/${id}`)
}
