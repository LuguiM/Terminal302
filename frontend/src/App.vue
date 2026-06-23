<script setup>
const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api'
</script>

<template>
  <main class="shell">
    <section class="panel">
      <p class="eyebrow">Terminal302</p>
      <h1>Base frontend lista</h1>
      <p>
        Vue 3 con Vite queda preparado para consumir la API Laravel desde
        <code>{{ apiBaseUrl }}</code>.
      </p>
    </section>
  </main>
</template>
