import api from './api'

export const getAdminOperators = (params = {}) => {
  return api.get('/admin/operadores', { params })
}

export const getAdminOperator = (id) => {
  return api.get(`/admin/operadores/${id}`)
}

export const toggleAdminOperatorStatus = (id, payload = {}) => {
  return api.patch(`/admin/operadores/${id}/toggle-status`, payload)
}

export const getAdminOperatorEmployees = (id, params = {}) => {
  return api.get(`/admin/operadores/${id}/empleados`, { params })
}

export const getAdminOperatorBuses = (id, params = {}) => {
  return api.get(`/admin/operadores/${id}/buses`, { params })
}

export const getAdminOperatorRoutes = (id, params = {}) => {
  return api.get(`/admin/operadores/${id}/rutas`, { params })
}
