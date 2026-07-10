<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import AppDataTable from '@/components/common/AppDataTable.vue'
import PageTitle from '@/components/common/PageTitle.vue'
import { notify } from '@/services/notifyService'
import {
  createAdminTicketTemplate,
  deleteAdminTicketTemplate,
  downloadAdminTicketTemplate,
  getAdminTicketTemplates,
  setDefaultAdminTicketTemplate,
} from '@/services/adminTicketTemplateService'
import TicketTemplateDefaultModal from '@/views/admin/ticket-templates/components/TicketTemplateDefaultModal.vue'
import TicketTemplateDeleteModal from '@/views/admin/ticket-templates/components/TicketTemplateDeleteModal.vue'
import TicketTemplatePreviewModal from '@/views/admin/ticket-templates/components/TicketTemplatePreviewModal.vue'
import TicketTemplateUploadModal from '@/views/admin/ticket-templates/components/TicketTemplateUploadModal.vue'

const router = useRouter()

const templates = ref([])
const loading = ref(false)
const error = ref('')
const page = ref(1)
const perPage = ref(15)
const total = ref(0)
const lastPage = ref(1)
const selectedTemplate = ref(null)
const actionLoading = ref(false)
const showUploadModal = ref(false)
const showPreviewModal = ref(false)
const showDeleteModal = ref(false)
const showDefaultModal = ref(false)

const headers = [
  { title: 'Nombre del archivo', key: 'nombre', sortable: false },
  { title: 'Fecha de carga', key: 'fechaCarga', sortable: false, align: 'center' },
  { title: 'Tamaño', key: 'tamano', sortable: false, align: 'center' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

const items = computed(() =>
  templates.value.map((template) => ({
    ...template,
    fechaCarga: formatDate(template.created_at),
    tamano: formatSize(template.image_size_bytes),
  })),
)

const getRow = (item) => item?.raw ?? item

const formatDate = (value) => {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('es-SV', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(value))
}

const formatSize = (bytes) => {
  if (!bytes) {
    return '-'
  }

  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const fetchTemplates = async () => {
  loading.value = true
  error.value = ''

  try {
    const { data } = await getAdminTicketTemplates({
      page: page.value,
      per_page: perPage.value,
    })

    templates.value = data.ticket_plantillas ?? []
    total.value = data.pagination?.total ?? 0
    lastPage.value = data.pagination?.last_page ?? 1
    page.value = data.pagination?.page ?? page.value
    perPage.value = data.pagination?.per_page ?? perPage.value
  } catch {
    templates.value = []
    total.value = 0
    lastPage.value = 1
    error.value = 'No se pudieron cargar las plantillas. Intente nuevamente.'
  } finally {
    loading.value = false
  }
}

const handlePageChange = (value) => {
  page.value = value
  fetchTemplates()
}

const handlePerPageChange = (value) => {
  perPage.value = value
  page.value = 1
  fetchTemplates()
}

const closeModals = () => {
  showUploadModal.value = false
  showPreviewModal.value = false
  showDeleteModal.value = false
  showDefaultModal.value = false
  selectedTemplate.value = null
}

const openPreview = (template) => {
  selectedTemplate.value = getRow(template)
  showPreviewModal.value = true
}

const openEditor = (template) => {
  const row = getRow(template)

  router.push({
    name: 'admin-ticket-template-editor',
    params: { id: row.id },
  })
}

const openDefaultModal = (template) => {
  selectedTemplate.value = getRow(template)
  showDefaultModal.value = true
}

const openDeleteModal = (template) => {
  selectedTemplate.value = getRow(template)
  showDeleteModal.value = true
}

const downloadFile = async (template) => {
  const row = getRow(template)
  const { data } = await downloadAdminTicketTemplate(row.id)
  const url = URL.createObjectURL(data)
  const link = document.createElement('a')

  link.href = url
  link.download = row.image_path?.split('/').pop() || row.nombre
  link.click()
  URL.revokeObjectURL(url)
}

const handleUpload = async (file) => {
  actionLoading.value = true

  try {
    const { data } = await createAdminTicketTemplate({
      file,
      nombre: file.name,
    })

    notify.success(data.message || 'Plantilla subida correctamente.')
    showUploadModal.value = false
    router.push({
      name: 'admin-ticket-template-editor',
      params: { id: data.ticket_plantilla.id },
    })
  } finally {
    actionLoading.value = false
  }
}

const handleSetDefault = async () => {
  if (!selectedTemplate.value?.id) {
    return
  }

  actionLoading.value = true

  try {
    const { data } = await setDefaultAdminTicketTemplate(selectedTemplate.value.id)
    notify.success(data.message || 'Plantilla predeterminada actualizada correctamente.')
    closeModals()
    await fetchTemplates()
  } finally {
    actionLoading.value = false
  }
}

const handleDelete = async () => {
  if (!selectedTemplate.value?.id) {
    return
  }

  actionLoading.value = true

  try {
    const { data } = await deleteAdminTicketTemplate(selectedTemplate.value.id)
    notify.success(data.message || 'Plantilla eliminada correctamente.')
    closeModals()
    await fetchTemplates()
  } finally {
    actionLoading.value = false
  }
}

onMounted(fetchTemplates)
</script>

<template>
  <v-container class="admin-ticket-templates-view" fluid>
    <PageTitle title="Plantilla de tickets" />

    <v-row class="mt-8 mb-8" justify="end">
      <v-col cols="12" sm="6" md="4" lg="3">
        <v-btn
          block
          class="pa-6"
          color="primary"
          rounded="lg"
          @click="showUploadModal = true"
        >
          Subir plantilla
        </v-btn>
      </v-col>
    </v-row>

    <v-alert
      v-if="error"
      class="mb-4"
      color="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <AppDataTable
      :headers="headers"
      :items="items"
      :last-page="lastPage"
      :loading="loading"
      no-data-text="No hay plantillas para mostrar."
      :page="page"
      :per-page="perPage"
      :total="total"
      @update:page="handlePageChange"
      @update:per-page="handlePerPageChange"
    >
      <template #item.nombre="{ item, value }">
        <div class="d-flex align-center ga-2">
          <span class="font-weight-bold">{{ value }}</span>

          <v-chip
            v-if="getRow(item).es_predeterminada"
            color="success"
            size="small"
            variant="tonal"
          >
            Predeterminada
          </v-chip>
        </div>
      </template>

      <template #item.actions="{ item }">
        <div class="ticket-template-actions">
          <v-tooltip text="Previsualizar">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Previsualizar plantilla"
                color="secondary"
                density="comfortable"
                icon="mdi-eye-outline"
                variant="text"
                @click="openPreview(item)"
              />
            </template>
          </v-tooltip>

          <v-tooltip text="Editar">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Editar plantilla"
                color="secondary"
                density="comfortable"
                icon="mdi-pencil-outline"
                variant="text"
                @click="openEditor(item)"
              />
            </template>
          </v-tooltip>

          <v-tooltip text="Descargar">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Descargar plantilla"
                color="secondary"
                density="comfortable"
                icon="mdi-download-outline"
                variant="text"
                @click="downloadFile(item)"
              />
            </template>
          </v-tooltip>

          <v-tooltip text="Establecer predeterminada">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Establecer plantilla predeterminada"
                color="secondary"
                density="comfortable"
                :disabled="getRow(item).es_predeterminada"
                icon="mdi-check-circle-outline"
                variant="text"
                @click="openDefaultModal(item)"
              />
            </template>
          </v-tooltip>

          <v-tooltip
            v-if="!getRow(item).es_predeterminada"
            text="Eliminar"
          >
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Eliminar plantilla"
                color="error"
                density="comfortable"
                icon="mdi-trash-can-outline"
                variant="text"
                @click="openDeleteModal(item)"
              />
            </template>
          </v-tooltip>
        </div>
      </template>
    </AppDataTable>

    <TicketTemplateUploadModal
      v-model="showUploadModal"
      :loading="actionLoading"
      @cancel="closeModals"
      @submit="handleUpload"
    />

    <TicketTemplatePreviewModal
      v-model="showPreviewModal"
      :template="selectedTemplate"
      @close="closeModals"
    />

    <TicketTemplateDefaultModal
      v-model="showDefaultModal"
      :loading="actionLoading"
      :template="selectedTemplate"
      @cancel="closeModals"
      @confirm="handleSetDefault"
    />

    <TicketTemplateDeleteModal
      v-model="showDeleteModal"
      :loading="actionLoading"
      :template="selectedTemplate"
      @cancel="closeModals"
      @confirm="handleDelete"
    />
  </v-container>
</template>

<style scoped>
.admin-ticket-templates-view {
  color: rgb(var(--v-theme-primary));
}

.ticket-template-actions {
  align-items: center;
  display: flex;
  gap: 4px;
  justify-content: center;
  white-space: nowrap;
}
</style>
