<script setup>
import { ref } from 'vue'

import AuthCard from '@/components/auth/AuthCard.vue'
import { forgotPassword } from '@/services/authService'

const form = ref(null)
const email = ref('')
const loading = ref(false)
const sent = ref(false)
const emailRules = [
  (value) => Boolean(value) || 'El correo electrónico es obligatorio.',
  (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) || 'Ingrese un correo electrónico válido.',
]

async function submit() {
  const validation = await form.value?.validate()

  if (!validation?.valid) {
    return
  }

  loading.value = true

  try {
    await forgotPassword({ email: email.value })
    sent.value = true
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthCard
    title="Recuperar contraseña"
    subtitle="Ingrese el correo asociado a su cuenta."
  >
    <v-alert
      v-if="sent"
      class="mb-7"
      color="success"
      type="success"
      variant="tonal"
    >
      Si el correo está registrado, recibirá un enlace para restablecer la contraseña.
    </v-alert>

    <v-form v-else ref="form" validate-on="submit" @submit.prevent="submit">
      <label class="text-primary font-weight-bold d-block mb-2" for="recovery-email">
        Correo electrónico
      </label>
      <v-text-field
        id="recovery-email"
        v-model="email"
        autocomplete="email"
        class="mb-7"
        density="comfortable"
        hide-details="auto"
        name="email"
        :rules="emailRules"
        type="email"
        variant="outlined"
      />

      <v-btn
        block
        color="primary"
        :loading="loading"
        rounded="lg"
        size="large"
        type="submit"
      >
        Enviar enlace
      </v-btn>
    </v-form>

    <div class="text-center mt-6">
      <v-btn color="blueLigth" :to="{ name: 'login' }" variant="text">
        Volver al inicio de sesión
      </v-btn>
    </div>
  </AuthCard>
</template>
