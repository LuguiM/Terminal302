<script setup>
import logoImage from '@/assets/logo.png'

defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <v-main class="auth-main bg-surface">
    <v-container class="auth-container justify-center" fluid>
      <v-card
        class="auth-card d-flex flex-column align-center px-5 py-8 px-sm-12 py-sm-12"
        elevation="0"
        rounded="xl"
        variant="outlined"
      >
        <v-img
          alt="Terminal 302"
          class="mb-7"
          max-width="240"
          :src="logoImage"
          width="52%"
        />

        <h1 class="text-primary text-center text-h4 font-weight-black mb-4">
          {{ title }}
        </h1>

        <p v-if="subtitle" class="text-secondary text-center mb-8">
          {{ subtitle }}
        </p>

        <div class="auth-card__content">
          <slot />
        </div>
      </v-card>
    </v-container>
  </v-main>
</template>

<style scoped>
.auth-card__content {
  width: min(100%, 520px);
}
</style>
