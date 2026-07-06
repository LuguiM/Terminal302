import api from './api'

export const getOperatorBuses = (params = {}) => {
  return api.get('/operador/buses', { params })
}

export const getOperatorBusTypes = () => {
  return api.get('/operador/tipo-buses')
}

export const createOperatorBus = (payload) => {
  return api.post('/operador/buses', payload)
}

export const updateOperatorBus = (id, payload) => {
  return api.put(`/operador/buses/${id}`, payload)
}

export const toggleOperatorBusStatus = (id) => {
  return api.patch(`/operador/buses/${id}/toggle-status`)
}
