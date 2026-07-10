import { defineStore } from 'pinia'

import { getMenu } from '@/services/menuService'

let pendingMenuRequest = null

const sortByOrder = (items = []) => {
  return [...items]
    .filter((item) => item.visible)
    .sort((first, second) => Number(first.orden) - Number(second.orden))
    .map((item) => ({
      ...item,
      dependencias: sortByOrder(item.dependencias ?? []),
    }))
}

export const flattenMenuRoutes = (items = []) => {
  return items.flatMap((item) => {
    const ownRoute = item.visible && item.ruta ? [item.ruta] : []
    const childRoutes = item.dependencias?.length
      ? flattenMenuRoutes(item.dependencias)
      : []

    return [...ownRoute, ...childRoutes]
  })
}

export const useMenuStore = defineStore('menu', {
  state: () => ({
    items: [],
    loading: false,
    error: null,
    allowedRoutes: [],
    loaded: false,
  }),

  actions: {
    async fetchMenu() {
      if (pendingMenuRequest) {
        return pendingMenuRequest
      }

      this.loading = true
      this.error = null

      pendingMenuRequest = (async () => {
        const { data } = await getMenu()
        const items = sortByOrder(data.menu_rutas ?? [])

        this.items = items
        this.allowedRoutes = [...new Set(flattenMenuRoutes(items))]
        this.loaded = true

        return items
      })()

      try {
        return await pendingMenuRequest
      } catch (error) {
        this.error = error
        throw error
      } finally {
        pendingMenuRequest = null
        this.loading = false
      }
    },

    resetMenu() {
      this.items = []
      this.error = null
      this.allowedRoutes = []
      this.loaded = false
    },
  },
})
