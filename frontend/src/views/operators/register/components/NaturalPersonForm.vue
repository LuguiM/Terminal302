<script setup>
import { computed, reactive } from "vue";
import { useVuelidate } from "@vuelidate/core";
import { helpers, maxLength, required } from "@vuelidate/validators";

import {
  formatDui,
  formatPhone,
  hasRepeatedDigits,
  hasValidDuiCheckDigit,
  hasValidDuiFormat,
  hasValidPhoneFormat,
} from "@/utils/salvadoranValidation";

defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["submit", "back"]);

const form = reactive({
  dui: "",
  telefono: "",
  nombre_comercial: "",
});

const optional = (validator) => (value) => !helpers.req(value) || validator(value);

const rules = computed(() => ({
  dui: {
    required: helpers.withMessage("El DUI es requerido.", required),
    format: helpers.withMessage(
      "El DUI debe tener formato ########-#.",
      optional(hasValidDuiFormat),
    ),
    repeated: helpers.withMessage("El DUI no puede contener un solo digito repetido.", (value) => !hasRepeatedDigits(value)),
    checkDigit: helpers.withMessage("El DUI no tiene un digito verificador valido.", optional(hasValidDuiCheckDigit)),
  },
  telefono: {
    required: helpers.withMessage("El telefono es requerido.", required),
    format: helpers.withMessage("El telefono debe tener formato ####-#### y comenzar con 2, 6 o 7.", optional(hasValidPhoneFormat)),
    repeated: helpers.withMessage("El telefono no puede contener un solo digito repetido.", (value) => !hasRepeatedDigits(value)),
  },
  nombre_comercial: {
    required: helpers.withMessage(
      "El nombre comercial es requerido.",
      required,
    ),
    maxLength: helpers.withMessage("El nombre comercial no debe superar 255 caracteres.", maxLength(255)),
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

  emit("submit", {
    ...form,
    nombre_comercial: form.nombre_comercial.trim(),
  });
};
</script>

<template>
  <v-form class="operator-register-form" @submit.prevent="submitForm">
    <label
      class="operator-register-label text-primary d-block mb-2"
      for="natural-dui"
    >
      Identificacion (DUI)
    </label>
    <v-text-field
      id="natural-dui"
      v-model="form.dui"
      bg-color="surface"
      class="mb-5"
      density="comfortable"
      :error-messages="v$.dui.$errors.map((error) => error.$message)"
      hide-details="auto"
      inputmode="numeric"
      maxlength="10"
      placeholder="########-#"
      rounded="lg"
      variant="outlined"
      @update:model-value="form.dui = formatDui($event)"
      @blur="v$.dui.$touch"
    />

    <label
      class="operator-register-label text-primary d-block mb-2"
      for="natural-phone"
    >
      Telefono
    </label>
    <v-text-field
      id="natural-phone"
      v-model="form.telefono"
      bg-color="surface"
      class="mb-5"
      density="comfortable"
      :error-messages="v$.telefono.$errors.map((error) => error.$message)"
      hide-details="auto"
      inputmode="numeric"
      maxlength="9"
      placeholder="####-####"
      rounded="lg"
      variant="outlined"
      @update:model-value="form.telefono = formatPhone($event)"
      @blur="v$.telefono.$touch"
    />

    <label
      class="operator-register-label text-primary d-block mb-2"
      for="natural-business-name"
    >
      Nombre comercial
    </label>
    <v-text-field
      id="natural-business-name"
      v-model="form.nombre_comercial"
      bg-color="surface"
      class="mb-8"
      density="comfortable"
      :error-messages="
        v$.nombre_comercial.$errors.map((error) => error.$message)
      "
      hide-details="auto"
      maxlength="255"
      placeholder="Ej: Luis"
      rounded="lg"
      variant="outlined"
      @blur="v$.nombre_comercial.$touch"
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
