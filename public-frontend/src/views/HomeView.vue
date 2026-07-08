<template>
  <section class="public-hero">
    <v-container class="public-hero__content">
      <div class="public-hero__copy">
        <p class="eyebrow">Portal publico de consultas</p>
        <h1>Terminal302</h1>
        <p class="public-hero__lead">
          Consulta tickets, revisa rutas disponibles y confirma horarios de salida desde un solo sitio.
        </p>

        <div class="public-hero__actions">
          <v-btn
            :to="{ name: 'ticket-search' }"
            color="primary"
            size="large"
          >
            <v-icon icon="mdi-ticket-confirmation-outline" start />
            Consultar ticket
          </v-btn>
          <v-btn
            :to="{ name: 'routes' }"
            color="primary"
            size="large"
            variant="outlined"
          >
            <v-icon icon="mdi-map-marker-path" start />
            Ver rutas
          </v-btn>
        </div>
      </div>

      <div class="public-hero__panel" aria-label="Resumen de consultas publicas">
        <div class="route-board">
          <div class="route-board__header">
            <span>Consulta publica</span>
            <v-icon icon="mdi-bus-clock" size="28" />
          </div>
          <div class="route-board__row">
            <span>Tickets</span>
            <strong>Codigo unico</strong>
          </div>
          <div class="route-board__row">
            <span>Rutas</span>
            <strong>Disponibles</strong>
          </div>
          <div class="route-board__row">
            <span>Horarios</span>
            <strong>Por salida</strong>
          </div>
        </div>
      </div>
    </v-container>
  </section>

  <section class="public-section">
    <v-container>
      <div class="feature-grid">
        <article
          v-for="feature in features"
          :key="feature.title"
          class="feature-card"
        >
          <v-icon :icon="feature.icon" color="primary" size="32" />
          <h2>{{ feature.title }}</h2>
          <p>{{ feature.text }}</p>
        </article>
      </div>
    </v-container>
  </section>
</template>

<script setup>
const features = [
  {
    icon: 'mdi-ticket-confirmation-outline',
    title: 'Consulta de tickets',
    text: 'Verifica la informacion publica de un ticket usando su codigo.',
  },
  {
    icon: 'mdi-map-marker-path',
    title: 'Rutas disponibles',
    text: 'Revisa las rutas activas que tienen horarios publicados.',
  },
  {
    icon: 'mdi-clock-outline',
    title: 'Horarios de salida',
    text: 'Consulta salidas por ruta, operador, unidad y dia programado.',
  },
]
</script>
