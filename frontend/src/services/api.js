import axios from 'axios'

import { notify } from '@/services/notifyService'
import { useUiStore } from '@/stores/uiStore'

export const TOKEN_STORAGE_KEY = 'access_token'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

const shouldUseGlobalLoader = (config) => !config?.skipGlobalLoader

const startGlobalLoader = (config) => {
  if (shouldUseGlobalLoader(config)) {
    const uiStore = useUiStore()

    uiStore.startRequest()
  }
}

const finishGlobalLoader = (config) => {
  if (shouldUseGlobalLoader(config)) {
    const uiStore = useUiStore()

    uiStore.finishRequest()
  }
}

api.interceptors.request.use((config) => {
  startGlobalLoader(config)

  const token = localStorage.getItem(TOKEN_STORAGE_KEY)

  if (token) {
    config.headers = config.headers ?? {}
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
}, (error) => {
  finishGlobalLoader(error.config)

  return Promise.reject(error)
})

api.interceptors.response.use(
  (response) => {
    finishGlobalLoader(response.config)

    return response
  },
  (error) => {
    finishGlobalLoader(error.config)

    if (error.response?.data?.code === 'OPERATOR_DISABLED') {
      localStorage.setItem('operator_access', JSON.stringify({
        blocked: true,
        reason: error.response.data.reason || null,
      }))

      if (window.location.pathname !== '/acceso-deshabilitado') {
        window.location.assign('/acceso-deshabilitado')
      }
    }

    if (!error.config?.suppressToast) {
      const message = getErrorMessage(error)

      notify.error(message)
    }

    return Promise.reject(error)
  },
)

const getErrorMessage = (error) => {
  const data = error.response?.data

  if (error.code === 'ERR_NETWORK' || (error.request && !error.response)) {
    return 'No se pudo conectar con el servidor. Por favor, verifica tu conexión a internet.'
  }

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
