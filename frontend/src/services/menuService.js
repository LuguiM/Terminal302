import api from './api'

export const getMenu = () => api.get('/me/menu-rutas')
