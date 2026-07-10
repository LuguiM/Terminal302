import api from './api'

export const getRoutes = (params = {}) => {
  return api.get('/admin/rutas', { params })
}

export const createRoute = (payload) => {
  return api.post('/admin/rutas', payload)
}

export const updateRoute = (id, payload) => {
  return api.put(`/admin/rutas/${id}`, payload)
}

export const toggleRouteStatus = (id) => {
  return api.patch(`/admin/rutas/${id}/toggle-status`)
}

export const deleteRoute = (id) => {
  return api.delete(`/admin/rutas/${id}`)
}
