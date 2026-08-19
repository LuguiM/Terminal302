<script setup>
import { computed, reactive } from "vue";
import { useVuelidate } from "@vuelidate/core";
import { helpers, maxLength, required } from "@vuelidate/validators";

defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["submit", "back"]);

const form = reactive({
  razon_social: "",
  representante_legal: "",
  nombre_comercial: "",
  direccion: "",
});

const rules = computed(() => ({
  razon_social: {
    required: helpers.withMessage("La razon social es requerida.", required),
    maxLength: helpers.withMessage("La razon social no debe superar 100 caracteres.", maxLength(100)),
  },
  representante_legal: {
    required: helpers.withMessage(
      "El representante legal es requerido.",
      required,
    ),
    maxLength: helpers.withMessage("El representante legal no debe superar 100 caracteres.", maxLength(100)),
  },
  nombre_comercial: {
    required: helpers.withMessage(
      "El nombre comercial es requerido.",
      required,
    ),
    maxLength: helpers.withMessage("El nombre comercial no debe superar 100 caracteres.", maxLength(100)),
  },
  direccion: {
    maxLength: helpers.withMessage("La direccion no debe superar 100 caracteres.", maxLength(100)),
  },
}));

const v$ = useVuelidate(rules, form, {
  $autoDirty: true,
});

const submitForm = async () => {
  const isValid = await v$.value.$validate();

  if (!isValid) {
    return;
  }

  emit("submit", Object.fromEntries(
    Object.entries(form).map(([key, value]) => [key, value.trim()]),
  ));
};
</script>

<template>
  <v-form class="operator-register-form" @submit.prevent="submitForm">
    <label
      class="operator-register-label text-primary d-block mb-2"
      for="legal-business-legal-name"
    >
      Razon social*
    </label>
    <v-text-field
      id="legal-business-legal-name"
      v-model="form.razon_social"
      bg-color="surface"
      class="mb-5"
      density="comfortable"
      :error-messages="v$.razon_social.$errors.map((error) => error.$message)"
      hide-details="auto"
      maxlength="100"
      placeholder="Nombre de la empresa"
      rounded="lg"
      variant="outlined"
      @blur="v$.razon_social.$touch"
    />

    <label
      class="operator-register-label text-primary d-block mb-2"
      for="legal-representative"
    >
      Representante legal*
    </label>
    <v-text-field
      id="legal-representative"
      v-model="form.representante_legal"
      bg-color="surface"
      class="mb-5"
      density="comfortable"
      :error-messages="
        v$.representante_legal.$errors.map((error) => error.$message)
      "
      hide-details="auto"
      maxlength="100"
      placeholder="Nombre del representante"
      rounded="lg"
      variant="outlined"
      @blur="v$.representante_legal.$touch"
    />

    <label
      class="operator-register-label text-primary d-block mb-2"
      for="legal-business-name"
    >
      Nombre comercial*
    </label>
    <v-text-field
      id="legal-business-name"
      v-model="form.nombre_comercial"
      bg-color="surface"
      class="mb-5"
      density="comfortable"
      :error-messages="
        v$.nombre_comercial.$errors.map((error) => error.$message)
      "
      hide-details="auto"
      maxlength="100"
      placeholder="Nombre comercial"
      rounded="lg"
      variant="outlined"
      @blur="v$.nombre_comercial.$touch"
    />

    <label
      class="operator-register-label text-primary d-block mb-2"
      for="legal-address"
    >
      Direccion
    </label>
    <v-text-field
      id="legal-address"
      v-model="form.direccion"
      bg-color="surface"
      class="mb-8"
      density="comfortable"
      hide-details="auto"
      :error-messages="v$.direccion.$errors.map((error) => error.$message)"
      maxlength="100"
      rounded="lg"
      variant="outlined"
      @blur="v$.direccion.$touch"
    />

    <div class="d-flex flex-column flex-sm-row justify-center ga-3">
      <v-btn
        class="operator-register-action text-none"
        rounded="lg"
        size="large"
        variant="outlined"
        @click="$emit('back')"
      >
        Volver
      </v-btn>

      <v-btn
        class="operator-register-action text-none"
        color="primary"
        :loading="loading"
        rounded="lg"
        size="large"
        type="submit"
      >
        Registrar
      </v-btn>
    </div>
  </v-form>
</template>

<style scoped>
.operator-register-form {
  width: min(100%, 560px);
}

.operator-register-title {
  font-size: clamp(1.5rem, 4vw, 1.9rem);
  line-height: 1.15;
}

.operator-register-label {
  font-weight: 800;
}

.operator-register-action {
  min-width: min(100%, 220px);
}
</style>
