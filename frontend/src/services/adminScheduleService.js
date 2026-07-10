import api from './api'

export const getAdminScheduleRoutes = (params = {}) => {
  return api.get('/admin/horarios/rutas', { params })
}

export const getAdminScheduleDays = () => {
  return api.get('/admin/horarios/dias')
}

export const getAdminScheduleRouteDays = (routeId) => {
  return api.get(`/admin/horarios/rutas/${routeId}`)
}

export const getAdminScheduleRouteOperators = (routeId) => {
  return api.get(`/admin/horarios/rutas/${routeId}/operadores`)
}

export const getAdminScheduleBuses = (params = {}) => {
  return api.get('/admin/horarios/buses', { params })
}

export const getAdminSchedules = (params = {}) => {
  return api.get('/admin/horarios', { params })
}

export const createAdminSchedule = (payload) => {
  return api.post('/admin/horarios', payload)
}

export const updateAdminSchedule = (id, payload) => {
  return api.put(`/admin/horarios/${id}`, payload)
}

export const deleteAdminSchedule = (id) => {
  return api.delete(`/admin/horarios/${id}`)
}
