import api from './api'

export const login = (credentials) => api.post('/login', credentials)

export const changeInitialPassword = (payload) => api.post('/change-initial-password', payload)

export const logout = (config = {}) => api.post('/logout', null, config)
