<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'

import logoImage from '@/assets/logo.png'
import { useAuthStore } from '@/stores/authStore'
import { useMenuStore } from '@/stores/menuStore'

const router = useRouter()
const authStore = useAuthStore()
const menuStore = useMenuStore()

const userName = computed(() => authStore.user?.name ?? 'Usuario')

const handleLogout = async () => {
  await authStore.logout()
  menuStore.resetMenu()
  router.push({ name: 'login' })
}
</script>

<template>
  <v-app-bar
    class="validator-navbar"
    color="surface"
    elevation="0"
    height="112"
  >
    <v-img
      alt="Terminal 302"
      class="validator-navbar__logo ml-3"
      max-width="130"
      :src="logoImage"
      width="34vw"
    />

    <v-spacer />

    <v-menu location="bottom end">
      <template #activator="{ props: activatorProps }">
        <v-btn
          v-bind="activatorProps"
          aria-label="Usuario autenticado"
          color="primary"
          icon="mdi-account-outline"
          rounded="circle"
          variant="flat"
        />
      </template>

      <v-card
        min-width="220"
        rounded="lg"
      >
        <v-list density="compact">
          <v-list-item
            prepend-icon="mdi-account-outline"
            :title="userName"
          />
        </v-list>
      </v-card>
    </v-menu>

    <v-btn
      aria-label="Cerrar sesion"
      class="mr-4"
      color="error"
      icon="mdi-logout"
      rounded="circle"
      variant="text"
      @click="handleLogout"
    />
  </v-app-bar>

  <v-main class="validator-main">
    <router-view />
  </v-main>
</template>

<style scoped>
.validator-navbar {
  border-bottom: 1px solid rgb(var(--v-theme-primary));
}

.validator-navbar__logo {
  flex: 0 0 auto;
}

.validator-main {
  background: rgb(var(--v-theme-surface));
  min-height: 100vh;
}
</style>
