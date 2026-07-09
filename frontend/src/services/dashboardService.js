import api from '@/services/api'

export const getAdminPassengerFlow = (params = {}) => {
  return api.get('/admin/dashboard/flujo-pasajeros', { params })
}

export const getOperatorPassengerFlow = (params = {}) => {
  return api.get('/operador/dashboard/flujo-pasajeros', { params })
}
