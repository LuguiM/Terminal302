<script setup>
import { Html5Qrcode } from 'html5-qrcode'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { notify } from '@/services/notifyService'
import { validateTicket } from '@/services/validatorTicketService'
import ValidatorCodeBottomSheet from './components/ValidatorCodeBottomSheet.vue'
import ValidatorFailureDetailsModal from './components/ValidatorFailureDetailsModal.vue'

const router = useRouter()

const scannerElementId = `validator-qr-reader-${Math.random().toString(36).slice(2)}`
const scannerRoot = ref(null)
const scanner = ref(null)
const cameraError = ref('')
const scannerReady = ref(false)
const validating = ref(false)
const manualSheetOpen = ref(false)
const detailsModalOpen = ref(false)
const failureDetails = ref(null)
const manualFailureDetails = ref(null)
const manualErrorMessage = ref('')
const torchSupported = ref(false)
const torchEnabled = ref(false)
const videoTrack = ref(null)

const failureMessage = computed(() => {
  return failureDetails.value?.message || ''
})

const shortFailureMessage = computed(() => {
  if (!failureMessage.value) {
    return 'No se pudo validar el ticket.'
  }

  return failureMessage.value
})

const goBack = () => {
  router.push({ name: 'validator-ticket-welcome' })
}

const buildFailureDetails = (error, codigoTicket) => {
  const response = error?.response

  return {
    codigo_ticket: codigoTicket,
    message: response?.data?.message || 'No se pudo validar el ticket.',
    errors: response?.data?.errors || {},
    status: response?.status,
    attempted_at: new Date().toISOString(),
  }
}

const pauseScanner = () => {
  try {
    if (scanner.value?.isScanning && typeof scanner.value.pause === 'function') {
      scanner.value.pause(true)
    }
  } catch {
    // Some browsers report an intermediate camera state. Validation can continue.
  }
}

const resumeScanner = () => {
  try {
    if (scanner.value?.isScanning && typeof scanner.value.resume === 'function') {
      scanner.value.resume()
    }
  } catch {
    // The scanner will be restarted by the user if the browser closes the stream.
  }
}

const submitValidation = async (codigoTicket, source = 'scanner') => {
  if (!codigoTicket || validating.value) {
    return false
  }

  validating.value = true
  manualErrorMessage.value = ''
  failureDetails.value = null

  try {
    const { data } = await validateTicket(
      { codigo_ticket: codigoTicket },
      { suppressToast: true },
    )

    notify.success(data.message || 'Ticket validado correctamente.')
    return true
  } catch (error) {
    const details = buildFailureDetails(error, codigoTicket)

    failureDetails.value = details
    manualErrorMessage.value = details.message
    notify.error(details.message)

    if (source === 'scanner') {
      detailsModalOpen.value = false
    }

    return false
  } finally {
    validating.value = false
  }
}

const handleManualValidation = async (codigoTicket) => {
  const valid = await submitValidation(codigoTicket, 'manual')

  if (valid) {
    manualSheetOpen.value = false
    manualFailureDetails.value = null
    manualErrorMessage.value = ''
  } else {
    manualFailureDetails.value = failureDetails.value
  }
}

const openManualValidation = () => {
  manualErrorMessage.value = ''
  manualFailureDetails.value = null
  manualSheetOpen.value = true
}

const showManualDetails = () => {
  failureDetails.value = manualFailureDetails.value
  detailsModalOpen.value = true
}

const handleScanSuccess = async (decodedText) => {
  const codigoTicket = decodedText?.trim()

  if (!codigoTicket || validating.value) {
    return
  }

  pauseScanner()
  await submitValidation(codigoTicket, 'scanner')
  resumeScanner()
}

const setTorchTrack = () => {
  const video = scannerRoot.value?.querySelector('video')
  const track = video?.srcObject?.getVideoTracks?.()[0]
  const capabilities = track?.getCapabilities?.()

  videoTrack.value = track || null
  torchSupported.value = Boolean(capabilities?.torch)
}

const toggleTorch = async () => {
  if (!videoTrack.value || !torchSupported.value) {
    return
  }

  const nextValue = !torchEnabled.value

  try {
    await videoTrack.value.applyConstraints({
      advanced: [{ torch: nextValue }],
    })
    torchEnabled.value = nextValue
  } catch {
    notify.warning('La linterna no esta disponible en este dispositivo.')
  }
}

const startScanner = async () => {
  cameraError.value = ''
  scannerReady.value = false

  try {
    await nextTick()

    scanner.value = new Html5Qrcode(scannerElementId, false)

    await scanner.value.start(
      { facingMode: 'environment' },
      {
        fps: 8,
        qrbox: { width: 250, height: 250 },
      },
      handleScanSuccess,
      () => {},
    )

    scannerReady.value = true
    setTimeout(setTorchTrack, 300)
  } catch {
    cameraError.value = 'No se pudo iniciar la camara. Revisa los permisos o valida por codigo.'
  }
}

const stopScanner = async () => {
  try {
    if (scanner.value?.isScanning) {
      await scanner.value.stop()
    }
  } catch {
    // Avoid blocking navigation if the browser already stopped the stream.
  }

  try {
    await scanner.value?.clear?.()
  } catch {
    // The library can throw if clear is called before the first render.
  }

  scanner.value = null
  videoTrack.value = null
  torchSupported.value = false
  torchEnabled.value = false
}

onMounted(startScanner)

onBeforeUnmount(stopScanner)
</script>

<template>
  <v-container class="validator-scanner">
    <div class="d-flex align-center justify-space-between mb-8">
      <v-btn
        aria-label="Volver"
        icon="mdi-arrow-left"
        variant="text"
        @click="goBack"
      />

      <h1 class="text-primary text-h5 font-weight-black">
        Escanear QR
      </h1>

      <v-btn
        :disabled="!torchSupported"
        :icon="torchEnabled ? 'mdi-flashlight-off' : 'mdi-flashlight'"
        variant="text"
        @click="toggleTorch"
      />
    </div>

    <v-alert
      v-if="cameraError"
      class="mb-4"
      color="warning"
      variant="tonal"
    >
      {{ cameraError }}
    </v-alert>

    <v-alert
      v-if="failureMessage"
      class="mb-4"
      color="error"
      variant="tonal"
    >
      <div class="d-flex align-center justify-space-between ga-3 flex-wrap">
        <span>{{ shortFailureMessage }}</span>
        <v-btn
          color="error"
          prepend-icon="mdi-alert-circle-outline"
          size="small"
          variant="flat"
          @click="detailsModalOpen = true"
        >
          Ver detalles
        </v-btn>
      </div>
    </v-alert>

    <div
      ref="scannerRoot"
      class="validator-scanner__camera bg-black rounded-xl d-flex align-center justify-center"
    >
      <div :id="scannerElementId" class="validator-scanner__reader" />

      <div
        v-if="!scannerReady && !cameraError"
        class="validator-scanner__placeholder d-flex flex-column align-center ga-3 text-white"
      >
        <v-progress-circular
          color="white"
          indeterminate
          size="44"
        />
        <span>Iniciando camara</span>
      </div>
    </div>

    <div class="d-flex justify-center my-10">
      <v-icon
        color="primary"
        icon="mdi-flashlight"
        size="56"
      />
    </div>

    <v-btn
      block
      class="mx-auto validator-scanner__manual-btn"
      color="secondary"
      rounded="lg"
      size="large"
      variant="outlined"
      @click="openManualValidation"
    >
      Validar por codigo
    </v-btn>

    <ValidatorCodeBottomSheet
      v-model="manualSheetOpen"
      :error-message="manualErrorMessage"
      :has-details="Boolean(manualFailureDetails)"
      :loading="validating"
      @show-details="showManualDetails"
      @submit="handleManualValidation"
    />

    <ValidatorFailureDetailsModal
      v-model="detailsModalOpen"
      :details="failureDetails"
    />
  </v-container>
</template>

<style scoped>
.validator-scanner {
  max-width: 520px;
  padding-top: 16px;
}

.validator-scanner__camera {
  border: 2px solid rgb(var(--v-theme-primary));
  min-height: 280px;
  overflow: hidden;
  position: relative;
}

.validator-scanner__reader {
  width: 100%;
}

.validator-scanner__placeholder {
  inset: 0;
  position: absolute;
}

.validator-scanner__manual-btn {
  max-width: 320px;
}

:deep(video) {
  object-fit: cover;
}

:deep(#qr-shaded-region) {
  border-color: rgba(255, 255, 255, 0.75) !important;
}
</style>
