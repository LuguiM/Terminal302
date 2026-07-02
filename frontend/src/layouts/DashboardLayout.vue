<script setup>
import { ref } from 'vue'

import AppNavbar from '@/components/layout/AppNavbar.vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'

const drawer = ref(false)
</script>

<template>
  <AppSidebar v-model="drawer" />

  <AppNavbar @toggle-sidebar="drawer = !drawer" />

  <v-main class="dashboard-main">
    <v-container
      class="dashboard-content"
      fluid
    >
      <router-view />
    </v-container>
  </v-main>
</template>
