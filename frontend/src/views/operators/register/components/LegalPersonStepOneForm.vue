<script setup>
import { computed, reactive } from "vue";
import { useVuelidate } from "@vuelidate/core";
import { email, helpers, required } from "@vuelidate/validators";

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

const rules = computed(() => ({
  nit: {
    required: helpers.withMessage("El NIT es requerido.", required),
    format: helpers.withMessage(
      "El NIT debe tener formato ####-######-###-#.",
      helpers.regex(/^\d{4}-\d{6}-\d{3}-\d$/),
    ),
  },
  telefono: {
    required: helpers.withMessage("El telefono es requerido.", required),
  },
  correo_administrativo: {
    email: helpers.withMessage("Ingrese un correo valido.", email),
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

  emit("submit", { ...form });
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
      placeholder="1234-567890-123-4"
      rounded="lg"
      variant="outlined"
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
      placeholder="1234-5678 o +503 2345-6789"
      rounded="lg"
      variant="outlined"
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
