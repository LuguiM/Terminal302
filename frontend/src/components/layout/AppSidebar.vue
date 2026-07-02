<script setup>
import { onMounted } from 'vue'
import { useDisplay } from 'vuetify'

import logoImage from '@/assets/logo.png'
import SidebarMenuItem from '@/components/layout/SidebarMenuItem.vue'
import { useMenuStore } from '@/stores/menuStore'

const drawer = defineModel({ type: Boolean, default: false })

const menuStore = useMenuStore()
const { mdAndUp } = useDisplay()

onMounted(() => {
  if (!menuStore.loaded && !menuStore.loading) {
    menuStore.fetchMenu().catch(() => {})
  }
})
</script>

<template>
  <v-navigation-drawer
    v-model="drawer"
    class="app-sidebar"
    :permanent="mdAndUp"
    :temporary="!mdAndUp"
    width="290"
  >
    <div class="sidebar-shell">
      <div class="sidebar-brand">
        <v-img
          alt="Terminal 302"
          class="sidebar-brand-logo"
          max-width="220"
          height="100"
          :src="logoImage"
          width="80%"
        />
      </div>

      <v-list
        class="sidebar-menu"
        density="comfortable"
        nav
      >
        <v-list-item
          class="sidebar-menu-item"
          prepend-icon="mdi-home-outline"
          rounded="0"
          title="Inicio"
          to="/inicio"
        />

        <SidebarMenuItem
          v-for="item in menuStore.items"
          :key="item.id"
          :item="item"
        />
      </v-list>

      <div class="sidebar-footer">
        &copy; 2026
      </div>
    </div>
  </v-navigation-drawer>
</template>
