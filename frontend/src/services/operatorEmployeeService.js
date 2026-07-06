import api from './api'

export const getOperatorEmployees = (params = {}) => {
  return api.get('/operador/empleados', { params })
}

export const createOperatorEmployee = (payload) => {
  return api.post('/operador/empleados', payload)
}

export const updateOperatorEmployee = (id, payload) => {
  return api.put(`/operador/empleados/${id}`, payload)
}

export const toggleOperatorEmployeeStatus = (id, payload = {}) => {
  return api.patch(`/operador/empleados/${id}/toggle-status`, payload)
}
