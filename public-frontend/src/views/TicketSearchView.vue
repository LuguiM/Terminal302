<template>
  <section class="py-6 py-md-12">
    <v-container class="public-narrow">
      <div class="d-grid align-center mb-8 public-subheader">
        <v-btn
          aria-label="Volver"
          icon="mdi-arrow-left"
          variant="text"
          @click="goBack"
        />
        <h1 class="text-primary text-h5 font-weight-black text-center mb-0">
          Consultar QR
        </h1>
        <span />
      </div>

      <v-alert
        v-if="errorMessage"
        class="mb-4"
        type="error"
        variant="tonal"
      >
        {{ errorMessage }}
      </v-alert>

      <v-alert
        v-if="cameraError"
        class="mb-4"
        color="warning"
        variant="tonal"
      >
        {{ cameraError }}
      </v-alert>

      <v-card class="pa-4 pa-md-6" elevation="0" rounded="lg" variant="outlined">
        <v-row align="stretch">
          <v-col cols="12" md="7">
            <div ref="scannerRoot" class="qr-camera">
              <div :id="scannerElementId" class="qr-camera__reader" />
              <div v-if="!scannerReady" class="qr-camera__placeholder">
                <v-icon icon="mdi-camera-outline" size="56" />
                <span>{{ cameraStatus }}</span>
              </div>
            </div>
          </v-col>

          <v-col cols="12" md="5">
            <div class="h-100 d-flex flex-column justify-center align-stretch ga-4">
              <v-btn
                :disabled="!torchSupported"
                :prepend-icon="torchEnabled ? 'mdi-flashlight-off' : 'mdi-flashlight'"
                color="primary"
                size="large"
                variant="tonal"
                @click="toggleTorch"
              >
                {{ torchEnabled ? 'Apagar linterna' : 'Usar linterna' }}
              </v-btn>

              <v-btn
                color="primary"
                prepend-icon="mdi-image-outline"
                size="large"
                variant="outlined"
                @click="triggerFilePicker"
              >
                Subir imagen
              </v-btn>

              <v-btn
                color="primary"
                prepend-icon="mdi-keyboard-outline"
                size="large"
                variant="flat"
                @click="openCodeSheet"
              >
                Ingresar codigo
              </v-btn>

              <p class="text-secondary text-body-2 mb-0">
                Puedes consultar escaneando el QR, cargando una imagen del ticket o escribiendo el codigo manualmente.
              </p>
            </div>
          </v-col>
        </v-row>

        <input
          ref="fileInput"
          accept="image/*"
          class="visually-hidden"
          type="file"
          @change="scanImage"
        />
        <div
          :id="`public-file-reader-${scannerElementId}`"
          class="visually-hidden"
        />
      </v-card>

      <v-dialog
        v-if="mdAndUp"
        v-model="codeSheetOpen"
        max-width="460"
      >
        <v-card
          class="mx-auto pa-5 pa-sm-7 public-code-sheet"
          rounded="xl"
        >
          <v-card-title class="text-center text-primary text-h5 font-weight-black mb-6">
            Consulta por codigo
          </v-card-title>

          <v-card-text class="pa-0">
            <v-text-field
              v-model.trim="ticketCode"
              autofocus
              clearable
              density="comfortable"
              :disabled="consulting"
              label="Codigo de ticket"
              prepend-inner-icon="mdi-keyboard-outline"
              variant="outlined"
              @keyup.enter="searchTicket"
            />
          </v-card-text>

          <v-card-actions class="pa-0 pt-4 d-flex flex-column ga-3">
            <v-btn
              block
              color="primary"
              :disabled="!ticketCode || consulting"
              :loading="consulting"
              rounded="lg"
              size="large"
              variant="flat"
              @click="searchTicket"
            >
              Consultar ticket
            </v-btn>

            <v-btn
              block
              color="secondary"
              :disabled="consulting"
              rounded="lg"
              size="large"
              variant="outlined"
              @click="codeSheetOpen = false"
            >
              Cancelar
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-bottom-sheet v-else v-model="codeSheetOpen">
        <v-card
          class="mx-auto pa-5 pa-sm-7 public-code-sheet"
          rounded="xl"
        >
          <v-card-title class="text-center text-primary text-h5 font-weight-black mb-6">
            Consulta por codigo
          </v-card-title>

          <v-card-text class="pa-0">
            <v-text-field
              v-model.trim="ticketCode"
              autofocus
              clearable
              density="comfortable"
              :disabled="consulting"
              label="Codigo de ticket"
              prepend-inner-icon="mdi-keyboard-outline"
              variant="outlined"
              @keyup.enter="searchTicket"
            />
          </v-card-text>

          <v-card-actions class="pa-0 pt-4 d-flex flex-column ga-3">
            <v-btn
              block
              color="primary"
              :disabled="!ticketCode || consulting"
              :loading="consulting"
              rounded="lg"
              size="large"
              variant="flat"
              @click="searchTicket"
            >
              Consultar ticket
            </v-btn>

            <v-btn
              block
              color="secondary"
              :disabled="consulting"
              rounded="lg"
              size="large"
              variant="outlined"
              @click="codeSheetOpen = false"
            >
              Cancelar
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-bottom-sheet>
    </v-container>
  </section>
</template>

<script setup>
import { Html5Qrcode } from 'html5-qrcode'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'

const router = useRouter()
const { mdAndUp } = useDisplay()
const scannerElementId = `public-qr-reader-${Math.random().toString(36).slice(2)}`

const scannerRoot = ref(null)
const scanner = ref(null)
const fileScanner = ref(null)
const fileInput = ref(null)
const ticketCode = ref('')
const cameraError = ref('')
const errorMessage = ref('')
const scannerReady = ref(false)
const consulting = ref(false)
const codeSheetOpen = ref(false)
const torchSupported = ref(false)
const torchEnabled = ref(false)
const videoTrack = ref(null)

const cameraStatus = computed(() => {
  if (cameraError.value) {
    return 'Camara no disponible'
  }

  return 'Iniciando camara'
})

const normalizeQrCode = (rawValue) => {
  const value = rawValue?.trim()

  if (!value) {
    return ''
  }

  try {
    const url = new URL(value)
    const segments = url.pathname.split('/').filter(Boolean)

    return segments.at(-1) || value
  } catch {
    return value
  }
}

const goBack = () => {
  router.push({ name: 'home' })
}

const goToTicket = (rawCode) => {
  const codigo = normalizeQrCode(rawCode)

  if (!codigo || consulting.value) {
    return
  }

  consulting.value = true
  router.push({
    name: 'ticket-detail',
    params: { codigo },
  })
}

const searchTicket = () => {
  goToTicket(ticketCode.value)
}

const openCodeSheet = () => {
  codeSheetOpen.value = true
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
    errorMessage.value = 'La linterna no esta disponible en este dispositivo.'
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
        qrbox: { width: 240, height: 240 },
      },
      goToTicket,
      () => {},
    )

    scannerReady.value = true
    setTimeout(setTorchTrack, 300)
  } catch {
    cameraError.value = 'No se pudo iniciar la camara. Puedes subir una imagen o consultar por codigo.'
  }
}

const stopScanner = async () => {
  try {
    if (scanner.value?.isScanning) {
      await scanner.value.stop()
    }
  } catch {
    // Browser camera streams can already be closed when leaving the view.
  }

  try {
    await scanner.value?.clear?.()
  } catch {
    // Ignore intermediate scanner states during cleanup.
  }

  scanner.value = null
  videoTrack.value = null
  torchSupported.value = false
  torchEnabled.value = false
}

const triggerFilePicker = () => {
  errorMessage.value = ''
  fileInput.value?.click()
}

const scanImage = async (event) => {
  const file = event.target.files?.[0]

  if (!file) {
    return
  }

  try {
    fileScanner.value = fileScanner.value || new Html5Qrcode(`public-file-reader-${scannerElementId}`)
    const decodedText = await fileScanner.value.scanFile(file, true)

    goToTicket(decodedText)
  } catch {
    errorMessage.value = 'No se encontro un QR valido en la imagen seleccionada.'
  } finally {
    event.target.value = ''
  }
}

onMounted(startScanner)
onBeforeUnmount(stopScanner)
</script>
