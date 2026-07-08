<template>
  <v-layout class="public-layout">
    <v-app-bar
      class="public-header"
      color="surface"
      elevation="0"
      height="72"
    >
      <v-container class="public-header__inner">
        <RouterLink class="public-brand" :to="{ name: 'home' }">
          <span class="public-brand__mark">302</span>
          <span class="public-brand__text">Terminal302</span>
        </RouterLink>

        <nav class="public-nav" aria-label="Navegacion publica">
          <v-btn
            v-for="item in navItems"
            :key="item.name"
            :to="{ name: item.name }"
            class="public-nav__button"
            color="primary"
            variant="text"
          >
            <v-icon :icon="item.icon" size="20" start />
            {{ item.label }}
          </v-btn>
        </nav>
      </v-container>
    </v-app-bar>

    <v-main class="public-main">
      <router-view />
    </v-main>
  </v-layout>
</template>

<script setup>
const navItems = [
  {
    name: 'home',
    label: 'Inicio',
    icon: 'mdi-home-outline',
  },
  {
    name: 'ticket-search',
    label: 'Ticket',
    icon: 'mdi-ticket-confirmation-outline',
  },
  {
    name: 'routes',
    label: 'Rutas',
    icon: 'mdi-map-marker-path',
  },
]
</script>
