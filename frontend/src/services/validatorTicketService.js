import api from './api'

export const validateTicket = (payload, config = {}) => {
  return api.post('/validador/tickets/validar', payload, config)
}

export const getValidatorValidations = (params = {}) => {
  return api.get('/validador/validaciones', { params })
}
