import api from './api'

export const getSellerTicketRoutes = () => {
  return api.get('/vendedor/rutas-disponibles')
}

export const getSellerRouteSchedules = (routeId) => {
  return api.get(`/vendedor/rutas/${routeId}/horarios-disponibles`)
}

export const getSellerTipoEnvios = () => {
  return api.get('/vendedor/tipo-envios')
}

export const createSellerTickets = (payload) => {
  return api.post('/vendedor/tickets', payload)
}

export const closeSellerSale = (ventaHorarioId, payload = {}) => {
  return api.patch(`/vendedor/ventas-horarios/${ventaHorarioId}/cerrar`, payload)
}

export const getSellerTicketPrintData = (ticketId) => {
  return api.get(`/vendedor/tickets/${ticketId}/print`)
}

export const retrySellerTicketDelivery = (ticketId) => {
  return api.post(`/vendedor/tickets/${ticketId}/retry-processing`)
}

export const downloadSellerTicketTemplateImage = (ticketId) => {
  return api.get(`/vendedor/tickets/${ticketId}/template-image`, {
    responseType: 'blob',
  })
}

export const getSellerTicketTemplateImageObjectUrl = async (ticketId) => {
  const { data } = await downloadSellerTicketTemplateImage(ticketId)

  return URL.createObjectURL(data)
}

export const downloadSellerTicketImage = (ticketId) => {
  return api.get(`/vendedor/tickets/${ticketId}/image`, {
    responseType: 'blob',
  })
}

export const getSellerTicketImageObjectUrl = async (ticketId) => {
  const { data } = await downloadSellerTicketImage(ticketId)

  return URL.createObjectURL(data)
}

export const getSellerDeliveries = (params = {}) => {
  return api.get('/vendedor/tickets/entregas', { params })
}

export const getSellerSalesHistory = (params = {}) => {
  return api.get('/vendedor/tickets', { params })
}
