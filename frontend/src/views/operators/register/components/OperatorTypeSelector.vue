<script setup>
defineEmits(["select"]);
</script>

<template>
  <div class="operator-type-selector  text-primary">
    <div class="text-center">
      <h1 class="text-secondary operator-register-title mb-0 font-weight-black">Bienvenido a Terminal 302</h1>
      <p class="mt-0">
        Para poder continuar dentro del sistema necesitas registrar un operador
      </p>
    </div>
    <h3
      class=" text-center font-weight-black mb-8"
    >
      Tipo de Operador
    </h3>

    <v-row class="operator-type-selector__options" justify="center">
      <v-col cols="12" sm="6">
        <v-btn
          block
          class="operator-type-selector__option text-primary"
          height="150"
          rounded="lg"
          variant="outlined"
          @click="$emit('select', 'natural')"
        >
          <div class="d-flex flex-column align-center ga-3">
            <v-icon icon="mdi-badge-account-outline" size="48" />
            <span class="operator-type-selector__label">Persona natural</span>
          </div>
        </v-btn>
      </v-col>

      <v-col cols="12" sm="6">
        <v-btn
          block
          class="operator-type-selector__option text-primary"
          height="150"
          rounded="lg"
          variant="outlined"
          @click="$emit('select', 'legal')"
        >
          <div class="d-flex flex-column align-center ga-3">
            <v-icon icon="mdi-scale-balance" size="48" />
            <span class="operator-type-selector__label">Persona juridica</span>
          </div>
        </v-btn>
      </v-col>
    </v-row>
  </div>
</template>

<style scoped>
.operator-type-selector {
  width: min(100%, 560px);
}

.operator-type-selector__option {
  border-color: rgb(var(--v-theme-primary));
  border-width: 2px;
  box-shadow: 4px 4px 0 rgba(var(--v-theme-primary), 0.2);
  text-transform: none;
}

.operator-type-selector__label {
  font-size: 1.2rem;
  font-weight: 900;
  line-height: 1.15;
}

.operator-register-title {
  font-size: clamp(1.6rem, 4vw, 2rem);
  line-height: 1.15;
}
</style>
