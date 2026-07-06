import { defineStore } from 'pinia'

export const useUiStore = defineStore('ui', {
  state: () => ({
    activeRequests: 0,
  }),

  getters: {
    isLoading: (state) => state.activeRequests > 0,
  },

  actions: {
    startRequest() {
      this.activeRequests += 1
    },

    finishRequest() {
      this.activeRequests = Math.max(0, this.activeRequests - 1)
    },
  },
})
