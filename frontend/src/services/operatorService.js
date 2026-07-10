import api from './api'

export const registerOperator = (payload) => api.post('/operador', payload)
