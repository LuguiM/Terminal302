<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useVuelidate } from '@vuelidate/core'
import { helpers, minLength, required, sameAs } from '@vuelidate/validators'

import AuthCard from '@/components/auth/AuthCard.vue'
import { notify } from '@/services/notifyService'
import { resetPassword } from '@/services/authService'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const showPassword = ref(false)
const showConfirmation = ref(false)
const email = computed(() => route.query.email?.toString() ?? '')
const token = computed(() => route.query.token?.toString() ?? '')
const invalidLink = computed(() => !email.value || !token.value)
const form = reactive({ password: '', password_confirmation: '' })

const rules = computed(() => ({
  password: {
    required: helpers.withMessage('La contraseña es obligatoria.', required),
    minLength: helpers.withMessage('Debe tener al menos 8 caracteres.', minLength(8)),
    uppercase: helpers.withMessage('Debe incluir una letra mayúscula.', helpers.regex(/[A-Z]/)),
    lowercase: helpers.withMessage('Debe incluir una letra minúscula.', helpers.regex(/[a-z]/)),
    number: helpers.withMessage('Debe incluir un número.', helpers.regex(/[0-9]/)),
    symbol: helpers.withMessage('Debe incluir un símbolo.', helpers.regex(/[^A-Za-z0-9]/)),
  },
  password_confirmation: {
    required: helpers.withMessage('Debe confirmar la contraseña.', required),
    sameAsPassword: helpers.withMessage('Las contraseñas deben coincidir.', sameAs(form.password)),
  },
}))
const v$ = useVuelidate(rules, form, { $autoDirty: true })

async function submit() {
  if (invalidLink.value || !await v$.value.$validate()) {
    return
  }

  loading.value = true

  try {
    const { data } = await resetPassword({
      email: email.value,
      token: token.value,
      ...form,
    })
    notify.success(data.message)
    await router.replace({ name: 'login' })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthCard
    title="Restablecer contraseña"
    subtitle="Defina una contraseña nueva para su cuenta."
  >
    <v-alert
      v-if="invalidLink"
      class="mb-6"
      color="error"
      type="error"
      variant="tonal"
    >
      El enlace de recuperación está incompleto o no es válido.
    </v-alert>

    <v-form v-else @submit.prevent="submit">
      <label class="text-primary font-weight-bold d-block mb-2" for="reset-password">
        Contraseña nueva
      </label>
      <v-text-field
        id="reset-password"
        v-model="form.password"
        :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
        autocomplete="new-password"
        class="mb-5"
        density="comfortable"
        :error-messages="v$.password.$errors.map((error) => error.$message)"
        hide-details="auto"
        name="password"
        :type="showPassword ? 'text' : 'password'"
        variant="outlined"
        @blur="v$.password.$touch"
        @click:append-inner="showPassword = !showPassword"
      />

      <label class="text-primary font-weight-bold d-block mb-2" for="reset-password-confirmation">
        Confirmar contraseña
      </label>
      <v-text-field
        id="reset-password-confirmation"
        v-model="form.password_confirmation"
        :append-inner-icon="showConfirmation ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
        autocomplete="new-password"
        class="mb-7"
        density="comfortable"
        :error-messages="v$.password_confirmation.$errors.map((error) => error.$message)"
        hide-details="auto"
        name="password_confirmation"
        :type="showConfirmation ? 'text' : 'password'"
        variant="outlined"
        @blur="v$.password_confirmation.$touch"
        @click:append-inner="showConfirmation = !showConfirmation"
      />

      <v-btn
        block
        color="primary"
        :loading="loading"
        rounded="lg"
        size="large"
        type="submit"
      >
        Guardar contraseña
      </v-btn>
    </v-form>

    <div class="text-center mt-6">
      <v-btn color="blueLigth" :to="{ name: 'login' }" variant="text">
        Volver al inicio de sesión
      </v-btn>
    </div>
  </AuthCard>
</template>
