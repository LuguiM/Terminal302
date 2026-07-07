<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import { getSellerTicketImageObjectUrl } from '@/services/sellerTicketService'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  tickets: {
    type: Array,
    default: () => [],
  },
})

defineEmits(['update:modelValue', 'done'])

const ticketImageUrls = ref({})
const imageLoading = ref(false)
const imageError = ref('')

const printableTickets = computed(() =>
  props.tickets
    .map((ticket) => ({
      id: ticket.id,
      codigo: ticket.codigo_ticket,
      src: ticketImageUrls.value[ticket.id] || ticket.image_url || ticket.print_url,
    }))
    .filter((ticket) => ticket.id && ticket.src),
)

const hasPrintableImages = computed(() => printableTickets.value.length > 0)

const revokeTicketImages = () => {
  Object.values(ticketImageUrls.value).forEach((url) => {
    if (url?.startsWith('blob:')) {
      URL.revokeObjectURL(url)
    }
  })

  ticketImageUrls.value = {}
}

const loadTicketImages = async () => {
  revokeTicketImages()
  imageError.value = ''

  if (!props.modelValue || props.tickets.length === 0) {
    return
  }

  imageLoading.value = true

  try {
    const entries = await Promise.all(
      props.tickets
        .filter((ticket) => ticket.id)
        .map(async (ticket) => [
          ticket.id,
          await getSellerTicketImageObjectUrl(ticket.id),
        ]),
    )

    ticketImageUrls.value = Object.fromEntries(entries)
  } catch {
    imageError.value = 'No se pudo cargar la imagen final del ticket.'
  } finally {
    imageLoading.value = false
  }
}

const escapeAttribute = (value) => String(value ?? '')
  .replaceAll('&', '&amp;')
  .replaceAll('"', '&quot;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')

const buildPrintDocument = () => `
  <!doctype html>
  <html>
    <head>
      <meta charset="utf-8">
      <title>Tickets</title>
      <style>
        @page {
          margin: 0;
          size: landscape;
        }

        * {
          box-sizing: border-box;
        }

        html,
        body {
          background: #ffffff;
          height: 100%;
          margin: 0;
          padding: 0;
          width: 100%;
        }

        .ticket-page {
          align-items: center;
          break-after: page;
          display: flex;
          height: 100%;
          justify-content: center;
          overflow: hidden;
          page-break-after: always;
          page-break-inside: avoid;
          width: 100%;
        }

        .ticket-page:last-child {
          break-after: auto;
          page-break-after: auto;
        }

        .ticket-image {
          display: block;
          max-height: 100%;
          max-width: 100%;
          object-fit: contain;
        }
      </style>
    </head>
    <body>
      ${printableTickets.value.map((ticket) => `
        <section class="ticket-page">
          <img
            alt="Ticket ${escapeAttribute(ticket.codigo)}"
            class="ticket-image"
            src="${escapeAttribute(ticket.src)}"
          >
        </section>
      `).join('')}
    </body>
  </html>
`

const printTickets = () => {
  if (!hasPrintableImages.value) {
    imageError.value = 'No hay imagenes de tickets disponibles para imprimir.'
    return
  }

  const iframe = document.createElement('iframe')

  iframe.style.border = '0'
  iframe.style.height = '0'
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'

  document.body.appendChild(iframe)

  const iframeDocument = iframe.contentWindow?.document

  if (!iframeDocument) {
    iframe.remove()
    return
  }

  iframeDocument.open()
  iframeDocument.write(buildPrintDocument())
  iframeDocument.close()

  setTimeout(() => {
    iframe.contentWindow?.focus()
    iframe.contentWindow?.print()

    setTimeout(() => {
      iframe.remove()
    }, 1000)
  }, 500)
}

watch(
  () => [props.modelValue, props.tickets.map((ticket) => ticket.id).join(',')],
  loadTicketImages,
  { immediate: true },
)

onBeforeUnmount(() => {
  revokeTicketImages()
})
</script>

<template>
  <v-dialog
    :model-value="modelValue"
    max-width="1120"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card
      class="seller-print-dialog-card pa-4 pa-sm-5 d-flex flex-column"
      rounded="lg"
    >
      <v-card-title class="text-center text-primary font-weight-black text-h5">
        Imprimir tickets
      </v-card-title>

      <v-card-text class="seller-print-dialog-body py-4">
        <v-progress-linear
          v-if="imageLoading"
          class="mb-4"
          color="primary"
          indeterminate
        />

        <v-alert
          v-if="imageError"
          class="mb-4"
          color="error"
          type="error"
          variant="tonal"
        >
          {{ imageError }}
        </v-alert>

        <v-alert
          v-if="tickets.length === 0"
          color="secondary"
          variant="tonal"
        >
          No hay tickets para imprimir.
        </v-alert>

        <v-alert
          v-else-if="!hasPrintableImages && !imageLoading"
          color="warning"
          variant="tonal"
        >
          No se pudo cargar la imagen final de estos tickets.
        </v-alert>

        <div
          v-else
          class="seller-print-preview-list d-flex flex-column ga-3"
        >
          <v-sheet
            v-for="ticket in printableTickets"
            :key="ticket.id"
            border
            class="seller-print-ticket-frame mx-auto overflow-auto"
            color="white"
            max-width="100%"
            rounded="lg"
          >
            <img
              alt="Ticket generado"
              class="seller-print-ticket-preview"
              :src="ticket.src"
            />
          </v-sheet>
        </div>
      </v-card-text>

      <v-card-actions class="justify-center ga-4 flex-wrap pt-2">
        <v-btn
          rounded="lg"
          variant="outlined"
          @click="$emit('done')"
        >
          Continuar
        </v-btn>

        <v-btn
          color="primary"
          :disabled="!hasPrintableImages || imageLoading"
          prepend-icon="mdi-printer"
          rounded="lg"
          variant="flat"
          @click="printTickets"
        >
          Imprimir
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.seller-print-dialog-card {
  max-height: min(86vh, 760px);
}

.seller-print-dialog-body {
  overflow-y: auto;
}

.seller-print-preview-list {
  align-items: center;
}

.seller-print-ticket-frame {
  width: min(560px, 100%);
}

.seller-print-ticket-preview {
  aspect-ratio: 2 / 1;
  display: block;
  max-height: 260px;
  object-fit: contain;
  width: 100%;
}

@media (max-width: 600px) {
  .seller-print-dialog-card {
    max-height: 90vh;
  }

  .seller-print-ticket-frame {
    width: 100%;
  }

  .seller-print-ticket-preview {
    max-height: 180px;
  }
}
</style>
