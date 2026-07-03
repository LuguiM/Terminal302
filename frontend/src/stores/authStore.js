import { defineStore } from 'pinia'

import { TOKEN_STORAGE_KEY } from '@/services/api'
import {
  changeInitialPassword,
  login as loginRequest,
  logout as logoutRequest,
} from '@/services/authService'

const USER_STORAGE_KEY = 'terminal302_user'
const MUST_CHANGE_PASSWORD_STORAGE_KEY = 'must_change_password'

const getStoredMustChangePassword = () => {
  return localStorage.getItem(MUST_CHANGE_PASSWORD_STORAGE_KEY) === 'true'
}

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
    mustChangePassword: getStoredMustChangePassword(),
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
      this.mustChangePassword = getStoredMustChangePassword()
    },

    async login(credentials) {
      this.loading = true
      this.error = null

      try {
        const { data } = await loginRequest(credentials)
        const accessToken = data.access_token
        const mustChangePassword = Boolean(
          data.must_change_password ?? data.user?.must_change_password,
        )

        this.accessToken = accessToken
        this.user = data.user ?? null
        this.mustChangePassword = mustChangePassword

        if (accessToken) {
          localStorage.setItem(TOKEN_STORAGE_KEY, accessToken)
        }

        if (this.user) {
          localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))
        } else {
          localStorage.removeItem(USER_STORAGE_KEY)
        }

        localStorage.setItem(MUST_CHANGE_PASSWORD_STORAGE_KEY, String(mustChangePassword))

        return data
      } catch (error) {
        this.error = error
        throw error
      } finally {
        this.loading = false
      }
    },

    async changePassword(payload) {
      this.loading = true
      this.error = null

      try {
        const { data } = await changeInitialPassword(payload)

        this.mustChangePassword = false

        if (data.user) {
          this.user = data.user
          localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))
        } else if (this.user) {
          this.user = {
            ...this.user,
            must_change_password: false,
          }
          localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))
        }

        localStorage.setItem(MUST_CHANGE_PASSWORD_STORAGE_KEY, 'false')

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
        this.mustChangePassword = false
        this.error = null
        localStorage.removeItem(TOKEN_STORAGE_KEY)
        localStorage.removeItem(USER_STORAGE_KEY)
        localStorage.removeItem(MUST_CHANGE_PASSWORD_STORAGE_KEY)
      }
    },
  },
})
