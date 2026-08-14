<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import logoImage from '@/assets/logo.png'
import { getAuthenticatedHomeRoute } from '@/router/authHome'
import { useAuthStore } from '@/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()
const refreshing = ref(false)
const loggingOut = ref(false)

const reason = computed(() => authStore.operatorAccess?.reason
  || 'Comunicate con el administrador de la terminal para restablecer tu acceso.')

const refreshAccess = async () => {
  refreshing.value = true

  try {
    await authStore.refreshSession()

    if (!authStore.operatorAccess?.blocked) {
      await router.replace(getAuthenticatedHomeRoute(authStore))
    }
  } catch (error) {
    if (error?.response?.status === 401) {
      await authStore.logout()
      await router.replace({ name: 'login' })
    }
  } finally {
    refreshing.value = false
  }
}

const handleLogout = async () => {
  loggingOut.value = true
  await authStore.logout()
  await router.replace({ name: 'login' })
}

onMounted(refreshAccess)
</script>

<template>
  <v-main class="disabled-access bg-surface">
    <v-container class="disabled-access__container d-flex align-center justify-center" fluid>
      <v-card
        class="disabled-access__card d-flex flex-column align-center text-center"
        color="surface"
        elevation="0"
        rounded="xl"
      >
        <v-progress-linear
          v-if="refreshing"
          absolute
          color="primary"
          indeterminate
        />

        <v-img
          alt="Terminal 302"
          class="disabled-access__logo"
          :src="logoImage"
          width="230"
        />

        <h1 class="disabled-access__title text-primary">
          Se ha desactivado tu acceso al sistema
        </h1>

        <p class="disabled-access__description text-primary">
          Este es el motivo por el que tu acceso ha sido desactivado:
        </p>

        <div class="disabled-access__reason text-left">
          <span class="disabled-access__reason-label d-block text-medium-emphasis">
            Motivo
          </span>
          <strong class="text-primary">{{ reason }}</strong>
        </div>

        <p class="disabled-access__help text-primary font-weight-bold">
          Comunicate con el administrador de la terminal para restablecer tu acceso
        </p>

        <v-btn
          color="primary"
          :loading="loggingOut"
          rounded="lg"
          size="large"
          variant="flat"
          @click="handleLogout"
        >
          Cerrar sesión
        </v-btn>
      </v-card>
    </v-container>
  </v-main>
</template>

<style scoped>
.disabled-access__container {
  min-height: 100vh;
  padding: 10px;
}

.disabled-access__card {
  border: 2px solid rgb(var(--v-theme-primary));
  max-width: 630px;
  min-height: min(806px, calc(100vh - 32px));
  padding: clamp(40px, 8vh, 105px) clamp(24px, 8vw, 105px) 48px;
  width: 100%;
}

.disabled-access__logo {
  flex: 0 0 auto;
  margin-bottom: clamp(42px, 7vh, 64px);
  max-height: 145px;
}

.disabled-access__title {
  font-size: clamp(1.55rem, 4vw, 2rem);
  font-style: italic;
  font-weight: 900;
  line-height: 1.08;
  max-width: 470px;
}

.disabled-access__description {
  font-size: 1.15rem;
  line-height: 1.35;
  margin: 28px 0 22px;
  max-width: 390px;
}

.disabled-access__reason {
  background: #f5f3f3;
  border: 1px solid #dedada;
  border-radius: 12px;
  min-height: 118px;
  padding: 12px 28px;
  width: 100%;
}

.disabled-access__reason-label {
  font-size: 0.8rem;
  margin-bottom: 17px;
}

.disabled-access__help {
  font-size: 1.05rem;
  line-height: 1.4;
  margin: 46px 0 30px;
  max-width: 440px;
}

@media (max-width: 599px) {
  .disabled-access__card {
    min-height: calc(100vh - 24px);
    padding: 36px 20px;
  }

  .disabled-access__logo {
    margin-bottom: 36px;
    width: 190px !important;
  }

  .disabled-access__help {
    margin-top: 36px;
  }
}
</style>
