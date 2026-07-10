import api from '@/services/api'

export const getPublicTicket = async (codigoTicket) => {
  const response = await api.get(`/public/tickets/${encodeURIComponent(codigoTicket)}`)

  return response.data.ticket
}
