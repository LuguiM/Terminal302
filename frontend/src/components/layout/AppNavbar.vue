<script setup>
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/authStore'
import { useMenuStore } from '@/stores/menuStore'

const emit = defineEmits(['toggle-sidebar'])

const router = useRouter()
const authStore = useAuthStore()
const menuStore = useMenuStore()

const handleLogout = async () => {
  await authStore.logout()
  menuStore.resetMenu()
  router.push({ name: 'login' })
}
</script>

<template>
  <v-app-bar
    class="app-navbar"
    color="surface"
    elevation="0"
    height="90"
  >
    <v-app-bar-nav-icon
      class="navbar-menu-button"
      icon="mdi-menu"
      variant="text"
      @click="emit('toggle-sidebar')"
    />

    <v-spacer />

    <v-btn
      class="logout-button"
      color="error"
      prepend-icon="mdi-logout"
      rounded="xl"
      variant="outlined"
      @click="handleLogout"
    >
      Cerrar sesión
    </v-btn>
  </v-app-bar>
</template>
