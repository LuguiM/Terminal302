<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";

import logoImage from "@/assets/logo.png";
import { notify } from "@/services/notifyService";
import { registerOperator } from "@/services/operatorService";
import { useAuthStore } from "@/stores/authStore";
import LegalPersonStepOneForm from "@/views/operators/register/components/LegalPersonStepOneForm.vue";
import LegalPersonStepTwoForm from "@/views/operators/register/components/LegalPersonStepTwoForm.vue";
import NaturalPersonForm from "@/views/operators/register/components/NaturalPersonForm.vue";
import OperatorTypeSelector from "@/views/operators/register/components/OperatorTypeSelector.vue";

const EMPRESA_TIPO_OPERADOR_ID = 1;
const PERSONA_TIPO_OPERADOR_ID = 2;

const router = useRouter();
const authStore = useAuthStore();

const operatorType = ref(null);
const legalStep = ref(1);
const legalStepOneData = ref(null);
const loading = ref(false);
const error = ref(null);

const selectOperatorType = (type) => {
  operatorType.value = type;
  legalStep.value = 1;
  error.value = null;
};

const handleBackToTypeSelector = () => {
  operatorType.value = null;
  legalStep.value = 1;
  error.value = null;
};

const handleLegalStepOneSubmit = (payload) => {
  legalStepOneData.value = payload;
  legalStep.value = 2;
  error.value = null;
};

const handleBackToLegalStepOne = () => {
  legalStep.value = 1;
  error.value = null;
};

const submitRegistration = async (payload) => {
  loading.value = true;
  error.value = null;

  try {
    const resp = await registerOperator(payload);
    if (resp.status === 200 || resp.status === 201) {
      authStore.setRequiresOperatorRegistration(false);
      authStore.setUserOperator(resp.data?.operador ?? null);
      notify.success("Operador registrado correctamente.");
      router.push({ name: "inicio" });
    }
  } catch (requestError) {
    error.value = getErrorMessage(requestError);
  } finally {
    loading.value = false;
  }
};

const handleNaturalSubmit = (payload) => {
  submitRegistration({
    tipo_operador_id: PERSONA_TIPO_OPERADOR_ID,
    dui: payload.dui,
    telefono: payload.telefono,
    nombre_comercial: payload.nombre_comercial,
  });
};

const handleLegalStepTwoSubmit = (payload) => {
  submitRegistration({
    tipo_operador_id: EMPRESA_TIPO_OPERADOR_ID,
    ...legalStepOneData.value,
    ...payload,
  });
};

const getErrorMessage = (requestError) => {
  return (
    requestError.response?.data?.message ??
    "No se pudo registrar el operador. Intente nuevamente."
  );
};
</script>

<template>
  <v-main class="bg-surface">
    <v-container
      class="operator-register-container d-flex align-center justify-center pa-4"
    >
      <v-card
        class="operator-register-card d-flex flex-column align-center pa-5 pa-sm-8 border-lg border-secondary border-opacity-75"
        elevation="0"
        rounded="lg"
      >
        <v-img
          alt="Terminal 302"
          class="operator-register-logo mb-2"
          max-width="180"
          :src="logoImage"
          width="42%"
        />

        <h1
          v-if="operatorType"
          class="text-primary text-center font-weight-black mb-6"
        >
          Registro persona
          {{ operatorType === "natural" ? "natural" : "juridica" }}
        </h1>

        <v-alert
          v-if="error"
          class="mb-6"
          color="error"
          type="error"
          variant="tonal"
          width="100%"
        >
          {{ error }}
        </v-alert>

        <OperatorTypeSelector
          v-if="!operatorType"
          @select="selectOperatorType"
        />

        <NaturalPersonForm
          v-else-if="operatorType === 'natural'"
          :loading="loading"
          @back="handleBackToTypeSelector"
          @submit="handleNaturalSubmit"
        />

        <v-stepper
          v-else
          v-model="legalStep"
          class="operator-register-stepper"
          color="primary"
          elevation="0"
        >
          <v-stepper-header class="operator-register-stepper__header">
            <v-stepper-item
              :complete="legalStep > 1"
              editable
              title="Datos juridicos"
              :value="1"
            />

            <v-divider />

            <v-stepper-item
              :disabled="!legalStepOneData"
              :editable="Boolean(legalStepOneData)"
              title="Datos de empresa"
              :value="2"
            />
          </v-stepper-header>

          <v-stepper-window>
            <v-stepper-window-item :value="1">
              <LegalPersonStepOneForm
                :initial-data="legalStepOneData ?? {}"
                @back="handleBackToTypeSelector"
                @submit="handleLegalStepOneSubmit"
              />
            </v-stepper-window-item>

            <v-stepper-window-item :value="2">
              <LegalPersonStepTwoForm
                :loading="loading"
                @back="handleBackToLegalStepOne"
                @submit="handleLegalStepTwoSubmit"
              />
            </v-stepper-window-item>
          </v-stepper-window>
        </v-stepper>
      </v-card>
    </v-container>
  </v-main>
</template>

<style scoped>
.operator-register-container {
  min-height: 100dvh;
}

.operator-register-card {
  overflow-y: auto;
  width: min(100%, 720px);
}

.operator-register-logo {
  flex: 0 0 auto;
}

.operator-register-stepper {
  margin-inline: auto;
  width: min(100%, 620px);
}

.operator-register-stepper__header {
  box-shadow: none;
}

.operator-register-stepper :deep(.v-stepper-window) {
  margin: 0;
}

.operator-register-stepper :deep(.v-stepper-window-item) {
  display: flex;
  justify-content: center;
  padding: 0;
}

@media (max-width: 599px) {
  .operator-register-stepper :deep(.v-stepper-item) {
    padding-inline: 8px;
  }

  .operator-register-stepper :deep(.v-stepper-item__title) {
    font-size: 0.78rem;
    line-height: 1.2;
  }
}
</style>
