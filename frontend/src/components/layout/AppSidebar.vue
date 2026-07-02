<script setup>
import { computed, onMounted } from 'vue'
import { useDisplay } from 'vuetify'

import SidebarMenuItem from '@/components/layout/SidebarMenuItem.vue'
import { useMenuStore } from '@/stores/menuStore'

const drawer = defineModel({ type: Boolean, default: false })

const menuStore = useMenuStore()
const { mdAndUp } = useDisplay()

const drawerModel = computed({
  get() {
    return mdAndUp.value ? true : drawer.value
  },
  set(value) {
    drawer.value = value
  },
})

onMounted(() => {
  if (!menuStore.loaded && !menuStore.loading) {
    menuStore.fetchMenu().catch(() => {})
  }
})
</script>

<template>
  <v-navigation-drawer
    v-model="drawerModel"
    class="app-sidebar"
    :permanent="mdAndUp"
    :temporary="!mdAndUp"
    width="332"
  >
    <div class="sidebar-shell">
      <div class="sidebar-brand">
        <v-icon
          class="sidebar-brand-icon"
          icon="mdi-bus"
        />
        <div class="sidebar-brand-text">
          TERMINAL 302
        </div>
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
