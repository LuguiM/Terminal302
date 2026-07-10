<template>
  <section class="public-hero">
    <v-container class="py-12 py-md-16">
      <v-row align="center" class="public-hero__row" justify="space-between">
        <v-col cols="12" md="6" lg="6">
          <p class="text-blueLigth text-subtitle-2 font-weight-black text-uppercase mb-4">
            Portal publico de consultas
          </p>
          <h1 class="public-hero__title text-primary font-weight-black mb-5">
            Terminal 302
          </h1>
          <p class="public-hero__lead text-secondary mb-8">
            Consulta tickets, revisa rutas disponibles y confirma horarios de salida desde un solo sitio.
          </p>

          <div class="d-flex flex-wrap ga-3">
            <v-btn
              :to="{ name: 'ticket-search' }"
              class="text-white"
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
        </v-col>

        <v-col cols="12" md="5">
          <v-sheet class="pa-5" color="greyLigth" rounded="lg">
            <div class="route-board">
              <div class="route-board__header">
                <span>Consulta publica</span>
                <v-icon icon="mdi-bus-clock" size="30" />
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
          </v-sheet>
        </v-col>
      </v-row>
    </v-container>
  </section>

  <section class="bg-greyLigth py-12 py-md-16">
    <v-container>
      <v-row>
        <v-col
          v-for="feature in features"
          :key="feature.title"
          cols="12"
          md="4"
        >
          <v-card class="h-100 pa-6" elevation="0" rounded="lg" variant="outlined">
            <v-icon :icon="feature.icon" color="primary" size="36" />
            <h2 class="text-primary text-h5 font-weight-black mt-8 mb-3">
              {{ feature.title }}
            </h2>
            <p class="text-secondary mb-0">
              {{ feature.text }}
            </p>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </section>
</template>

<script setup>
const features = [
  {
    icon: 'mdi-ticket-confirmation-outline',
    title: 'Consulta de tickets',
    text: 'Verifica la informacion publica de un ticket usando QR, imagen o codigo.',
  },
  {
    icon: 'mdi-map-marker-path',
    title: 'Rutas disponibles',
    text: 'Revisa rutas activas que tienen horarios publicados.',
  },
  {
    icon: 'mdi-clock-outline',
    title: 'Horarios de salida',
    text: 'Consulta salidas por ruta, operador, unidad y dia programado.',
  },
]
</script>
