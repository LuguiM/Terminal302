<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'

import loginImage from '@/assets/login-img.png'
import logoImage from '@/assets/logo.png'
import { getAuthenticatedHomeRoute } from '@/router/authHome'
import { notify } from '@/services/notifyService'
import { useAuthStore } from '@/stores/authStore'

const REMEMBERED_USER_KEY = 'remembered_user'

const router = useRouter()
const authStore = useAuthStore()
const form = ref(null)
const showPassword = ref(false)
const email = ref(localStorage.getItem(REMEMBERED_USER_KEY) ?? '')
const password = ref('')
const rememberUser = ref(Boolean(email.value))

const requiredRules = [(value) => Boolean(value) || 'Este campo es requerido.']
const passwordType = computed(() => (showPassword.value ? 'text' : 'password'))

const submitLogin = async () => {
  const validation = await form.value?.validate()

  if (!validation?.valid) {
    return
  }

  try {
    await authStore.login({
      email: email.value,
      password: password.value,
    })

    if (rememberUser.value) {
      localStorage.setItem(REMEMBERED_USER_KEY, email.value)
    } else {
      localStorage.removeItem(REMEMBERED_USER_KEY)
    }

    notify.success('Inicio de sesion correcto.')

    router.push(getAuthenticatedHomeRoute(authStore))
  } catch {
    password.value = ''
  }
}
</script>

<template>
  <v-main class="auth-main bg-surface">
    <v-container
      class="login-container pa-0"
      fluid
    >
      <v-row
        class="login-shell ma-0"
        no-gutters
      >
        <v-col
          class="d-none d-lg-flex login-image-panel"
          cols="12"
          lg="6"
        >
          <v-img
            cover
            height="100vh"
            :src="loginImage"
          />
        </v-col>

        <v-col
          class="login-form-panel d-flex justify-center pa-3 pa-sm-4"
          cols="12"
          lg="6"
        >
          <v-card
            class="login-card d-flex flex-column align-center justify-center px-5 py-6 px-sm-12"
            elevation="0"
            rounded="xl"
          >
            <v-img
              alt="Terminal 302"
              class="login-logo mb-8"
              max-width="280"
              :src="logoImage"
              width="60%"
            />

            <v-form
              ref="form"
              class="login-form"
              validate-on="submit"
              @submit.prevent="submitLogin"
            >
              <label
                class="login-label text-primary d-block mb-2"
                for="login-email"
              >
                Correo
              </label>

              <v-text-field
                id="login-email"
                v-model="email"
                autocomplete="username"
                bg-color="surface"
                class="mb-2"
                density="comfortable"
                hide-details="auto"
                rounded="lg"
                :rules="requiredRules"
                variant="outlined"
              />

              <v-checkbox
                v-model="rememberUser"
                class="mb-5"
                color="primary"
                density="compact"
                hide-details
                label="Recordar usuario"
              />

              <label
                class="login-label text-primary d-block mb-2"
                for="login-password"
              >
                Contraseña
              </label>

              <v-text-field
                id="login-password"
                v-model="password"
                autocomplete="current-password"
                bg-color="surface"
                class="mb-2"
                density="comfortable"
                hide-details="auto"
                :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
                rounded="lg"
                :rules="requiredRules"
                :type="passwordType"
                variant="outlined"
                @click:append-inner="showPassword = !showPassword"
              />

              <v-btn
                class="text-none px-0 mb-8 mb-lg-12"
                color="blueLigth"
                slim
                type="button"
                variant="text"
              >
                Recuperar contraseña
              </v-btn>

              <div class="d-flex justify-center">
                <v-btn
                  class="login-submit text-none"
                  color="primary"
                  :loading="authStore.loading"
                  rounded="lg"
                  size="x-large"
                  type="submit"
                  variant="flat"
                >
                  Ingresar
                </v-btn>
              </div>
            </v-form>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </v-main>
</template>
