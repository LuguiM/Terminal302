<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useVuelidate } from '@vuelidate/core'
import { helpers, minLength, required, sameAs } from '@vuelidate/validators'

import logoImage from '@/assets/logo.png'
import { notify } from '@/services/notifyService'
import { useAuthStore } from '@/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()
const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const form = reactive({
  password: '',
  password_confirmation: '',
})

const rules = computed(() => ({
  password: {
    required: helpers.withMessage('La contraseña es requerida.', required),
    minLength: helpers.withMessage('La contraseña debe tener al menos 8 caracteres.', minLength(8)),
    hasLetters: helpers.withMessage(
      'La contraseña debe incluir al menos una letra.',
      helpers.regex(/[A-Za-z]/),
    ),
    hasNumbers: helpers.withMessage(
      'La contraseña debe incluir al menos un numero.',
      helpers.regex(/[0-9]/),
    ),
  },
  password_confirmation: {
    required: helpers.withMessage('Debe repetir la contraseña.', required),
    sameAsPassword: helpers.withMessage('Las contraseñas deben coincidir.', sameAs(form.password)),
  },
}))

const v$ = useVuelidate(rules, form, {
  $autoDirty: true,
})

const passwordType = computed(() => (showPassword.value ? 'text' : 'password'))
const passwordConfirmationType = computed(() => (
  showPasswordConfirmation.value ? 'text' : 'password'
))

const submitChangePassword = async () => {
  const isValid = await v$.value.$validate()

  if (!isValid) {
    return
  }

  try {
    await authStore.changePassword({
      password: form.password,
      password_confirmation: form.password_confirmation,
    })

    notify.success('Contraseña actualizada correctamente.')
    router.push({ name: 'inicio' })
  } catch {
    form.password = ''
    form.password_confirmation = ''
    v$.value.$reset()
  }
}
</script>

<template>
  <v-main class="change-password-main bg-surface">
    <v-container
      class="change-password-container d-flex align-center justify-center pa-4"
      fluid
    >
      <v-card
        class="change-password-card d-flex flex-column align-center px-5 py-8 px-sm-12 py-sm-12"
        elevation="0"
        rounded="xl"
      >
        <v-img
          alt="Terminal 302"
          class="mb-7"
          max-width="280"
          :src="logoImage"
          width="56%"
        />

        <h1 class="change-password-title text-primary text-center font-weight-black mb-8">
          Cambio de contraseña
        </h1>

        <p class="text-primary text-center text-h6 mb-8">
          Necesita cambiar la contraseña antes de continuar
        </p>

        <v-form
          class="change-password-form"
          @submit.prevent="submitChangePassword"
        >
          <label
            class="change-password-label text-primary d-block mb-2"
            for="new-password"
          >
            Contraseña
          </label>

          <v-text-field
            id="new-password"
            v-model="form.password"
            autocomplete="new-password"
            bg-color="surface"
            class="mb-6"
            density="comfortable"
            :error-messages="v$.password.$errors.map((error) => error.$message)"
            hide-details="auto"
            :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
            rounded="lg"
            :type="passwordType"
            variant="outlined"
            @blur="v$.password.$touch"
            @click:append-inner="showPassword = !showPassword"
          />

          <label
            class="change-password-label text-primary d-block mb-2"
            for="password-confirmation"
          >
            Repetir contraseña
          </label>

          <v-text-field
            id="password-confirmation"
            v-model="form.password_confirmation"
            autocomplete="new-password"
            bg-color="surface"
            class="mb-12"
            density="comfortable"
            :error-messages="v$.password_confirmation.$errors.map((error) => error.$message)"
            hide-details="auto"
            :append-inner-icon="showPasswordConfirmation ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
            rounded="lg"
            :type="passwordConfirmationType"
            variant="outlined"
            @blur="v$.password_confirmation.$touch"
            @click:append-inner="showPasswordConfirmation = !showPasswordConfirmation"
          />

          <div class="d-flex justify-center">
            <v-btn
              class="change-password-submit text-none"
              color="primary"
              :loading="authStore.loading"
              rounded="lg"
              size="x-large"
              type="submit"
              variant="flat"
            >
              Continuar
            </v-btn>
          </div>
        </v-form>
      </v-card>
    </v-container>
  </v-main>
</template>

<style scoped>
.change-password-container {
  min-height: 100vh;
}

.change-password-card {
  border: 2px solid rgb(var(--v-theme-primary));
  min-height: min(100%, 900px);
  width: min(100%, 860px);
}

.change-password-title {
  font-size: clamp(2rem, 5vw, 2.8rem);
  line-height: 1.15;
}

.change-password-form {
  width: min(100%, 720px);
}

.change-password-label {
  font-size: 1.1rem;
  font-weight: 800;
}

.change-password-submit {
  font-weight: 800;
  min-width: min(100%, 290px);
}
</style>
