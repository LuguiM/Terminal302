import api from './api'

export const getUsers = (params = {}) => {
  return api.get('/admin/users', { params })
}

export const createUser = (payload) => {
  return api.post('/admin/users', payload)
}

export const updateUser = (id, payload) => {
  return api.put(`/admin/users/${id}`, payload)
}

export const resetUserPassword = (id) => {
  return api.patch(`/admin/users/${id}/reset-password`)
}

export const toggleUserStatus = (id) => {
  return api.patch(`/admin/users/${id}/toggle-status`)
}
