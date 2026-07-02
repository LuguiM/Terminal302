import { defineStore } from 'pinia'

import { TOKEN_STORAGE_KEY } from '@/services/api'
import { login as loginRequest, logout as logoutRequest } from '@/services/authService'

const USER_STORAGE_KEY = 'terminal302_user'

const getStoredUser = () => {
  const storedUser = localStorage.getItem(USER_STORAGE_KEY)

  if (!storedUser) {
    return null
  }

  try {
    return JSON.parse(storedUser)
  } catch {
    localStorage.removeItem(USER_STORAGE_KEY)
    return null
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: getStoredUser(),
    accessToken: localStorage.getItem(TOKEN_STORAGE_KEY),
    loading: false,
    error: null,
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.accessToken),
  },

  actions: {
    loadSession() {
      this.accessToken = localStorage.getItem(TOKEN_STORAGE_KEY)
      this.user = getStoredUser()
    },

    async login(credentials) {
      this.loading = true
      this.error = null

      try {
        const { data } = await loginRequest(credentials)
        const accessToken = data.access_token

        this.accessToken = accessToken
        this.user = data.user ?? null

        if (accessToken) {
          localStorage.setItem(TOKEN_STORAGE_KEY, accessToken)
        }

        if (this.user) {
          localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))
        } else {
          localStorage.removeItem(USER_STORAGE_KEY)
        }

        return data
      } catch (error) {
        this.error = error
        throw error
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        if (this.accessToken) {
          await logoutRequest({ suppressToast: true }).catch(() => {})
        }
      } finally {
        this.user = null
        this.accessToken = null
        this.error = null
        localStorage.removeItem(TOKEN_STORAGE_KEY)
        localStorage.removeItem(USER_STORAGE_KEY)
      }
    },
  },
})
