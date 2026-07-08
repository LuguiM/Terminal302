<template>
  <v-layout class="public-layout">
    <v-navigation-drawer
      v-model="drawerOpen"
      location="right"
      temporary
    >
      <div class="pa-6 border-b">
        <RouterLink class="public-brand" :to="{ name: 'home' }" @click="drawerOpen = false">
          <img class="public-brand__logo" src="@/assets/logo.png" alt="Terminal 302" />
        </RouterLink>
      </div>

      <v-list class="pa-3" nav>
        <v-list-item
          v-for="item in navItems"
          :key="item.name"
          :active="isActiveItem(item)"
          :prepend-icon="item.icon"
          :title="item.label"
          rounded="lg"
          @click="navigateTo(item)"
        />
      </v-list>
    </v-navigation-drawer>

    <v-app-bar
      border
      color="surface"
      elevation="0"
      height="92"
    >
      <v-container class="d-flex align-center justify-space-between">
        <RouterLink class="public-brand" :to="{ name: 'home' }">
          <img class="public-brand__logo" src="@/assets/logo.png" alt="Terminal 302" />
        </RouterLink>

        <nav class="public-nav" aria-label="Navegacion publica">
          <v-btn
            v-for="item in navItems"
            :key="item.name"
            class="font-weight-black text-none"
            color="primary"
            rounded="lg"
            :variant="isActiveItem(item) ? 'tonal' : 'text'"
            @click="navigateTo(item)"
          >
            <v-icon :icon="item.icon" size="20" start />
            {{ item.label }}
          </v-btn>
        </nav>

        <v-btn
          aria-label="Abrir menu"
          class="public-menu-button"
          color="primary"
          icon="mdi-menu"
          variant="text"
          @click="drawerOpen = true"
        />
      </v-container>
    </v-app-bar>

    <v-main class="bg-white">
      <router-view />
    </v-main>
  </v-layout>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const drawerOpen = ref(false)

const navItems = [
  {
    name: 'home',
    label: 'Inicio',
    icon: 'mdi-home-outline',
  },
  {
    name: 'ticket-search',
    label: 'Consulta',
    icon: 'mdi-ticket-confirmation-outline',
  },
  {
    name: 'routes',
    label: 'Rutas',
    icon: 'mdi-map-marker-path',
  },
]

const activeGroups = {
  home: ['home'],
  'ticket-search': ['ticket-search', 'ticket-detail'],
  routes: ['routes', 'route-schedules'],
}

const isActiveItem = (item) => {
  return activeGroups[item.name]?.includes(route.name)
}

const navigateTo = (item) => {
  drawerOpen.value = false
  router.push({ name: item.name })
}
</script>
