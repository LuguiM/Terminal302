import api from './api'

const apiOrigin = new URL(import.meta.env.VITE_API_URL, window.location.origin).origin

const resolveAssetUrl = (url) => {
  if (!url || /^https?:\/\//i.test(url)) {
    return url
  }

  return `${apiOrigin}${url.startsWith('/') ? url : `/${url}`}`
}

const normalizeTemplate = (template) => {
  if (!template) {
    return template
  }

  return {
    ...template,
    image_url: resolveAssetUrl(template.image_url),
    download_url: resolveAssetUrl(template.download_url),
  }
}

const normalizeTemplateResponse = (response) => {
  if (response.data?.ticket_plantilla) {
    response.data.ticket_plantilla = normalizeTemplate(response.data.ticket_plantilla)
  }

  if (Array.isArray(response.data?.ticket_plantillas)) {
    response.data.ticket_plantillas = response.data.ticket_plantillas.map(normalizeTemplate)
  }

  return response
}

const appendLocations = (formData, locations = {}) => {
  Object.entries(locations).forEach(([key, value]) => {
    formData.append(key, JSON.stringify(value))
  })
}

export const getAdminTicketTemplates = (params = {}) => {
  return api.get('/admin/ticket-plantillas', { params })
    .then(normalizeTemplateResponse)
}

export const getAdminTicketTemplate = (id) => {
  return api.get(`/admin/ticket-plantillas/${id}`)
    .then(normalizeTemplateResponse)
}

export const createAdminTicketTemplate = ({ file, nombre, es_predeterminada = false }) => {
  const formData = new FormData()

  formData.append('nombre', nombre)
  formData.append('image', file)
  formData.append('es_predeterminada', es_predeterminada ? '1' : '0')

  return api.post('/admin/ticket-plantillas', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }).then(normalizeTemplateResponse)
}

export const updateAdminTicketTemplate = (id, payload) => {
  const formData = new FormData()

  formData.append('_method', 'PUT')
  formData.append('nombre', payload.nombre)
  formData.append('es_predeterminada', payload.es_predeterminada ? '1' : '0')

  if (payload.image) {
    formData.append('image', payload.image)
  }

  appendLocations(formData, payload.locations)

  return api.post(`/admin/ticket-plantillas/${id}`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  }).then(normalizeTemplateResponse)
}

export const downloadAdminTicketTemplate = (id) => {
  return api.get(`/admin/ticket-plantillas/${id}/download`, {
    responseType: 'blob',
  })
}

export const getAdminTicketTemplateImageObjectUrl = async (id) => {
  const { data } = await downloadAdminTicketTemplate(id)

  return URL.createObjectURL(data)
}

export const setDefaultAdminTicketTemplate = (id) => {
  return api.patch(`/admin/ticket-plantillas/${id}/set-default`)
    .then(normalizeTemplateResponse)
}

export const deleteAdminTicketTemplate = (id) => {
  return api.delete(`/admin/ticket-plantillas/${id}`)
}
