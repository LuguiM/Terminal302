<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const userName = computed(() => authStore.user?.name || 'Usuario')
const operator = computed(() => (
  authStore.user?.operador
  ?? authStore.user?.operador_empleado?.operador
  ?? null
))
const operatorName = computed(() => (
  operator.value?.nombre_comercial
  ?? operator.value?.razon_social
  ?? ''
))

const openScanner = () => {
  router.push({ name: 'validator-ticket-scanner' })
}
</script>

<template>
  <v-container class="validator-welcome d-flex flex-column">
    <div class="d-flex align-start ga-3 mb-10">
      <v-avatar
        class="mt-1"
        color="primary"
        size="44"
      >
        <v-icon
          color="white"
          icon="mdi-account"
        />
      </v-avatar>
      <div class="overflow-hidden">
        <h1 class="text-primary text-h5 text-sm-h4 font-weight-black">
          {{ userName }}
        </h1>

        <div
          v-if="operatorName"
          class="d-flex align-center ga-2 mt-2 text-secondary font-weight-bold validator-welcome__operator"
        >
          <v-icon
            icon="mdi-domain"
            size="20"
          />
          <span class="text-truncate">{{ operatorName }}</span>
        </div>
      </div>
    </div>

    <v-btn
      class="validator-welcome__card align-self-center"
      color="primary"
      min-height="180"
      rounded="xl"
      variant="flat"
      @click="openScanner"
    >
      <div class="d-flex flex-column align-center justify-center ga-5">
        <span class="text-h4 font-weight-black">Validar ticket</span>
        <v-icon
          icon="mdi-qrcode-scan"
          size="48"
        />
      </div>
    </v-btn>
  </v-container>
</template>

<style scoped>
.validator-welcome {
  max-width: 520px;
  min-height: calc(100vh - 160px);
  padding-top: 32px;
}

.validator-welcome__card {
  box-shadow: 0 8px 18px rgba(0, 18, 51, 0.24);
  width: min(100%, 430px);
}

.validator-welcome__operator {
  max-width: min(100%, 360px);
}
</style>
