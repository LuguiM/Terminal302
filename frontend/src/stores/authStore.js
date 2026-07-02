import { defineStore } from 'pinia'

import api, { TOKEN_STORAGE_KEY } from '@/services/api'

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
    token: localStorage.getItem(TOKEN_STORAGE_KEY),
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.token),
  },

  actions: {
    async login(credentials) {
      this.loading = true

      try {
        const { data } = await api.post('/login', credentials)

        this.token = data.access_token
        this.user = data.user

        localStorage.setItem(TOKEN_STORAGE_KEY, this.token)
        localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(this.user))

        return data
      } finally {
        this.loading = false
      }
    },

    async logout() {
      try {
        if (this.token) {
          await api.post('/logout', null, { suppressToast: true }).catch(() => {})
        }
      } finally {
        this.user = null
        this.token = null
        localStorage.removeItem(TOKEN_STORAGE_KEY)
        localStorage.removeItem(USER_STORAGE_KEY)
      }
    },
  },
})
