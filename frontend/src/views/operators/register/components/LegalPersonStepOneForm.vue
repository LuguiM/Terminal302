<script setup>
import { computed, reactive } from "vue";
import { useVuelidate } from "@vuelidate/core";
import { email, helpers, maxLength, required } from "@vuelidate/validators";

import {
  formatNit,
  formatPhone,
  hasRepeatedDigits,
  hasValidNitCheckDigit,
  hasValidNitFormat,
  hasValidPhoneFormat,
} from "@/utils/salvadoranValidation";

const props = defineProps({
  initialData: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(["submit", "back"]);

const form = reactive({
  nit: props.initialData.nit ?? "",
  telefono: props.initialData.telefono ?? "",
  correo_administrativo: props.initialData.correo_administrativo ?? "",
});

const optional = (validator) => (value) => !helpers.req(value) || validator(value);

const rules = computed(() => ({
  nit: {
    required: helpers.withMessage("El NIT es requerido.", required),
    format: helpers.withMessage(
      "El NIT debe tener formato ####-######-###-#.",
      optional(hasValidNitFormat),
    ),
    repeated: helpers.withMessage("El NIT no puede contener un solo digito repetido.", (value) => !hasRepeatedDigits(value)),
    checkDigit: helpers.withMessage("El NIT no tiene un digito verificador valido.", optional(hasValidNitCheckDigit)),
  },
  telefono: {
    required: helpers.withMessage("El telefono es requerido.", required),
    format: helpers.withMessage("El telefono debe tener formato ####-#### y comenzar con 2, 6 o 7.", optional(hasValidPhoneFormat)),
    repeated: helpers.withMessage("El telefono no puede contener un solo digito repetido.", (value) => !hasRepeatedDigits(value)),
  },
  correo_administrativo: {
    email: helpers.withMessage("Ingrese un correo valido.", email),
    maxLength: helpers.withMessage("El correo no debe superar 50 caracteres.", maxLength(50)),
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
    correo_administrativo: form.correo_administrativo.trim(),
  });
};
</script>

<template>
  <v-form class="operator-register-form" @submit.prevent="submitForm">
    <label
      class="operator-register-label text-primary d-block mb-2"
      for="legal-nit"
    >
      NIT (Numero de Identificacion Tributaria)*
    </label>
    <v-text-field
      id="legal-nit"
      v-model="form.nit"
      bg-color="surface"
      class="mb-5"
      density="comfortable"
      :error-messages="v$.nit.$errors.map((error) => error.$message)"
      hide-details="auto"
      inputmode="numeric"
      maxlength="17"
      placeholder="xxxx-xxxxxx-xxx-x"
      rounded="lg"
      variant="outlined"
      @update:model-value="form.nit = formatNit($event)"
      @blur="v$.nit.$touch"
    />

    <label
      class="operator-register-label text-primary d-block mb-2"
      for="legal-phone"
    >
      Telefono*
    </label>
    <v-text-field
      id="legal-phone"
      v-model="form.telefono"
      bg-color="surface"
      class="mb-5"
      density="comfortable"
      :error-messages="v$.telefono.$errors.map((error) => error.$message)"
      hide-details="auto"
      inputmode="numeric"
      maxlength="9"
      placeholder="xxxx-xxxx"
      rounded="lg"
      variant="outlined"
      @update:model-value="form.telefono = formatPhone($event)"
      @blur="v$.telefono.$touch"
    />

    <label
      class="operator-register-label text-primary d-block mb-2"
      for="legal-email"
    >
      Correo administrativo
    </label>
    <v-text-field
      id="legal-email"
      v-model="form.correo_administrativo"
      bg-color="surface"
      class="mb-8"
      density="comfortable"
      :error-messages="
        v$.correo_administrativo.$errors.map((error) => error.$message)
      "
      hide-details="auto"
      maxlength="50"
      placeholder="example@example.com"
      rounded="lg"
      variant="outlined"
      @blur="v$.correo_administrativo.$touch"
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
        rounded="lg"
        size="large"
        type="submit"
      >
        Continuar
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
