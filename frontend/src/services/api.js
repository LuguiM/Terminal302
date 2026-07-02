import axios from 'axios'

import { notify } from '@/services/notifyService'

export const TOKEN_STORAGE_KEY = 'access_token'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_STORAGE_KEY)

  if (token) {
    config.headers = config.headers ?? {}
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (!error.config?.suppressToast) {
      const message = getErrorMessage(error)

      notify.error(message)
    }

    return Promise.reject(error)
  },
)

const getErrorMessage = (error) => {
  const data = error.response?.data

  if (data?.errors) {
    const firstError = Object.values(data.errors)
      .flat()
      .find(Boolean)

    if (firstError) {
      return firstError
    }
  }

  if (data?.message) {
    return data.message
  }

  if (error.message) {
    return error.message
  }

  return 'Ocurrió un error inesperado.'
}

export default api
