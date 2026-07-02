<template>
  <v-main class="error-main">
    <v-container class="error-container">
      <v-card
        class="error-card"
        elevation="0"
      >
        <v-card-title class="text-h4 font-weight-bold">
          403
        </v-card-title>

        <v-card-text>
          No tienes permisos para acceder a esta sección.
        </v-card-text>

        <v-card-actions>
          <v-btn
            color="primary"
            to="/inicio"
            variant="flat"
          >
            Volver al inicio
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-container>
  </v-main>
</template>
