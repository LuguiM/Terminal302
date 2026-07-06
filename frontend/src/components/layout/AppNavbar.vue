<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/authStore'
import { useMenuStore } from '@/stores/menuStore'

const emit = defineEmits(['toggle-sidebar'])

const router = useRouter()
const authStore = useAuthStore()
const menuStore = useMenuStore()

const userName = computed(() => authStore.user?.name ?? 'Usuario')
const userEmail = computed(() => authStore.user?.email ?? '')
const operator = computed(() => (
  authStore.user?.operador
  ?? authStore.user?.operador_empleado?.operador
  ?? null
))
const operatorName = computed(() => operator.value?.nombre_comercial ?? operator.value?.razon_social ?? '')

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

    <v-menu location="bottom end">
      <template #activator="{ props: activatorProps }">
        <div
          v-bind="activatorProps"
          class="navbar-user d-flex align-center ga-3 text-primary overflow-hidden flex-shrink-1"
          role="button"
        >
          <v-avatar
            color="primary"
            size="40"
          >
            <v-icon
              color="surface"
              icon="mdi-account-outline"
            />
          </v-avatar>

          <div class="d-none d-md-flex flex-column overflow-hidden">
            <span class="text-body-2 font-weight-bold text-truncate">
              {{ userName }}
            </span>

            <span
              v-if="operatorName"
              class="text-caption text-secondary font-weight-bold text-truncate"
            >
              {{ operatorName }}
            </span>
          </div>
        </div>
      </template>

      <v-card
        min-width="260"
        rounded="lg"
      >
        <v-list density="compact">
          <v-list-item
            prepend-icon="mdi-account-outline"
            :subtitle="userEmail"
            :title="userName"
          />

          <v-list-item
            v-if="operatorName"
            prepend-icon="mdi-domain"
            :title="operatorName"
          />
        </v-list>
      </v-card>
    </v-menu>

    <v-btn
      class="logout-icon-button d-flex d-sm-none"
      color="error"
      icon="mdi-logout"
      rounded="circle"
      variant="outlined"
      @click="handleLogout"
    />

    <v-btn
      class="logout-button d-none d-sm-inline-flex"
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
