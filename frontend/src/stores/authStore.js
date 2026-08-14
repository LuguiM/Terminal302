import { defineStore } from 'pinia'

import { TOKEN_STORAGE_KEY } from '@/services/api'
import {
  changeInitialPassword,
  getCurrentUser,
  login as loginRequest,
  logout as logoutRequest,
} from '@/services/authService'

const USER_STORAGE_KEY = 'terminal302_user'
const MUST_CHANGE_PASSWORD_STORAGE_KEY = 'must_change_password'
const REQUIRES_OPERATOR_REGISTRATION_STORAGE_KEY = 'requires_operator_registration'
const OPERATOR_ACCESS_STORAGE_KEY = 'operator_access'

const defaultOperatorAccess = () => ({ blocked: false, reason: null })

const getStoredOperatorAccess = () => {
  try {
    return JSON.parse(localStorage.getItem(OPERATOR_ACCESS_STORAGE_KEY)) ?? defaultOperatorAccess()
  } catch {
    return defaultOperatorAccess()
  }
}

const getStoredMustChangePassword = () => {
  return localStorage.getItem(MUST_CHANGE_PASSWORD_STORAGE_KEY) === 'true'
}

const getStoredRequiresOperatorRegistration = () => {
  return localStorage.getItem(REQUIRES_OPERATOR_REGISTRATION_STORAGE_KEY) === 'true'
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
    requiresOperatorRegistration: getStoredRequiresOperatorRegistration(),
    operatorAccess: getStoredOperatorAccess(),
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
      this.requiresOperatorRegistration = getStoredRequiresOperatorRegistration()
      this.operatorAccess = getStoredOperatorAccess()
    },

    setRequiresOperatorRegistration(value) {
      this.requiresOperatorRegistration = Boolean(value)
      localStorage.setItem(
        REQUIRES_OPERATOR_REGISTRATION_STORAGE_KEY,
        String(this.requiresOperatorRegistration),
      )
    },

    setOperatorAccess(value) {
      this.operatorAccess = {
        blocked: Boolean(value?.blocked),
        reason: value?.reason || null,
      }
      localStorage.setItem(OPERATOR_ACCESS_STORAGE_KEY, JSON.stringify(this.operatorAccess))
    },

    setUserOperator(operator) {
      if (!this.user) {
        return
      }

      this.user = {
        ...this.user,
        operador: operator,
      }

      localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))
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
        const requiresOperatorRegistration = Boolean(data.requires_operator_registration)

        this.accessToken = accessToken
        this.user = data.user ?? null
        this.mustChangePassword = mustChangePassword
        this.requiresOperatorRegistration = requiresOperatorRegistration
        this.setOperatorAccess(data.operator_access)

        if (accessToken) {
          localStorage.setItem(TOKEN_STORAGE_KEY, accessToken)
        }

        if (this.user) {
          localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))
        } else {
          localStorage.removeItem(USER_STORAGE_KEY)
        }

        localStorage.setItem(MUST_CHANGE_PASSWORD_STORAGE_KEY, String(mustChangePassword))
        localStorage.setItem(
          REQUIRES_OPERATOR_REGISTRATION_STORAGE_KEY,
          String(requiresOperatorRegistration),
        )

        return data
      } catch (error) {
        this.error = error
        throw error
      } finally {
        this.loading = false
      }
    },

    async refreshSession() {
      const { data } = await getCurrentUser({ suppressToast: true, skipGlobalLoader: true })

      this.user = data.data ?? this.user
      this.setOperatorAccess(data.operator_access)

      if (this.user) {
        localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))
      }

      return data
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
        this.requiresOperatorRegistration = false
        this.operatorAccess = defaultOperatorAccess()
        this.error = null
        localStorage.removeItem(TOKEN_STORAGE_KEY)
        localStorage.removeItem(USER_STORAGE_KEY)
        localStorage.removeItem(MUST_CHANGE_PASSWORD_STORAGE_KEY)
        localStorage.removeItem(REQUIRES_OPERATOR_REGISTRATION_STORAGE_KEY)
        localStorage.removeItem(OPERATOR_ACCESS_STORAGE_KEY)
      }
    },
  },
})
