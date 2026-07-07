<script setup>
import { computed, onMounted, ref } from "vue";

import AppDataTable from "@/components/common/AppDataTable.vue";
import PageTitle from "@/components/common/PageTitle.vue";
import StatusChip from "@/components/common/StatusChip.vue";
import { notify } from "@/services/notifyService";
import {
  createUser,
  getRoles,
  getUsers,
  resetUserPassword,
  toggleUserStatus,
  updateUser,
} from "@/services/userService";
import UserDeleteModal from "@/views/admin/users/components/UserDeleteModal.vue";
import UserFormModal from "@/views/admin/users/components/UserFormModal.vue";
import UserResetPasswordModal from "@/views/admin/users/components/UserResetPasswordModal.vue";
import UserStatusModal from "@/views/admin/users/components/UserStatusModal.vue";

const users = ref([]);
const loading = ref(false);
const error = ref(null);
const search = ref("");
const page = ref(1);
const perPage = ref(15);
const total = ref(0);
const lastPage = ref(1);
const selectedUser = ref(null);
const actionLoading = ref(false);

const showFormModal = ref(false);
const showResetPasswordModal = ref(false);
const showDeleteModal = ref(false);
const showStatusModal = ref(false);
const formMode = ref("create");

const roles = ref([]);
const rolesLoading = ref(false);
const rolesError = ref("");
const assignableRoles = computed(() =>
  roles.value.filter((role) => role.nombre?.toLowerCase() !== "validador"),
);

const usersTableHeaders = [
  { title: "Nombre", key: "name", sortable: false },
  { title: "Email", key: "email", sortable: false },
  { title: "Rol", key: "roleName", sortable: false },
  { title: "Estado", key: "estado", sortable: false, align: "center" },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const usersTableItems = computed(() =>
  users.value.map((user) => ({
    ...user,
    roleName: user.role?.nombre ?? "",
    estado: user.estado?.nombre ?? "",
  })),
);

const getRow = (item) => item?.raw ?? item;

const getEstado = (item) => getRow(item)?.estado ?? "";

const isActiveStatus = (status) => status === "Activo";

const isValidatorRole = (item) => getRow(item)?.roleName?.toLowerCase() === "validador";

const selectedUserIsActive = computed(() => isActiveStatus(selectedUser.value?.estado));

const fetchUsers = async () => {
  loading.value = true;
  error.value = null;

  try {
    const { data } = await getUsers({
      page: page.value,
      per_page: perPage.value,
      search: search.value || undefined,
    });

    users.value = data.users ?? [];
    total.value = data.pagination?.total ?? 0;
    lastPage.value = data.pagination?.last_page ?? 1;
    page.value = data.pagination?.page ?? page.value;
    perPage.value = data.pagination?.per_page ?? perPage.value;
  } catch {
    error.value = "No se pudieron cargar los usuarios. Intente nuevamente.";
    users.value = [];
    total.value = 0;
    lastPage.value = 1;
  } finally {
    loading.value = false;
  }
};

const fetchRoles = async () => {
  rolesLoading.value = true;
  rolesError.value = "";

  try {
    const { data } = await getRoles();

    roles.value = data.roles ?? [];
  } catch {
    roles.value = [];
    rolesError.value = "No se pudieron cargar los roles. Intente nuevamente.";
  } finally {
    rolesLoading.value = false;
  }
};

const ensureRolesLoaded = () => {
  if (!rolesLoading.value && roles.value.length === 0) {
    fetchRoles();
  }
};

const handleSearch = () => {
  page.value = 1;
  fetchUsers();
};

const handleClear = () => {
  search.value = "";
  page.value = 1;
  fetchUsers();
};

const handlePageChange = (value) => {
  page.value = value;
  fetchUsers();
};

const handlePerPageChange = (value) => {
  perPage.value = value;
  page.value = 1;
  fetchUsers();
};

const closeModals = () => {
  showFormModal.value = false;
  showResetPasswordModal.value = false;
  showDeleteModal.value = false;
  showStatusModal.value = false;
  selectedUser.value = null;
};

const openCreateModal = () => {
  selectedUser.value = null;
  formMode.value = "create";
  ensureRolesLoaded();
  showFormModal.value = true;
};

const openEditModal = (user) => {
  selectedUser.value = getRow(user);
  formMode.value = "edit";
  ensureRolesLoaded();
  showFormModal.value = true;
};

const openResetPasswordModal = (user) => {
  selectedUser.value = getRow(user);
  showResetPasswordModal.value = true;
};

const openDeleteModal = (user) => {
  selectedUser.value = getRow(user);
  showDeleteModal.value = true;
};

const openToggleStatusModal = (user) => {
  selectedUser.value = getRow(user);
  showStatusModal.value = true;
};

const handleCreateUser = async (payload) => {
  actionLoading.value = true;

  try {
    const { data } = await createUser(payload);
    notify.success(data.message || "Usuario creado correctamente.");
    closeModals();
    await fetchUsers();
  } finally {
    actionLoading.value = false;
  }
};

const handleEditUser = async (payload) => {
  actionLoading.value = true;

  try {
    const { data } = await updateUser(payload.id, {
      name: payload.name,
      email: payload.email,
    });
    notify.success(data.message || "Usuario actualizado correctamente.");
    closeModals();
    await fetchUsers();
  } finally {
    actionLoading.value = false;
  }
};

const handleFormSubmit = (payload) => {
  if (formMode.value === "edit") {
    handleEditUser(payload);
    return;
  }

  handleCreateUser(payload);
};

const handleResetPassword = async () => {
  if (!selectedUser.value?.id) {
    return;
  }

  actionLoading.value = true;

  try {
    const { data } = await resetUserPassword(selectedUser.value.id);
    notify.success(data.message || "Contraseña restablecida correctamente.");
    closeModals();
    await fetchUsers();
  } finally {
    actionLoading.value = false;
  }
};

const handleToggleStatus = async () => {
  if (!selectedUser.value?.id) {
    return;
  }

  actionLoading.value = true;

  try {
    const { data } = await toggleUserStatus(selectedUser.value.id);
    notify.success(data.message || "Estado del usuario actualizado correctamente.");
    closeModals();
    await fetchUsers();
  } finally {
    actionLoading.value = false;
  }
};

const handleDeleteUser = () => {
  // TODO: conectar cuando exista DELETE /admin/users/{id} en el backend.
  notify.warning("La eliminación de usuarios todavía no tiene endpoint disponible.");
  closeModals();
};

onMounted(() => {
  fetchUsers();
  fetchRoles();
});
</script>

<template>
  <v-container class="users-view" fluid>
    <PageTitle title="Gestión de usuarios" />

    <v-row align="center" class="mt-8 mb-8" justify="space-between">
      <v-col cols="12" lg="7">
        <v-row align="center">
          <v-col cols="12" md="6">
            <v-text-field
              v-model="search"
              density="comfortable"
              hide-details
              placeholder="Buscar"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              @keyup.enter="handleSearch"
            />
          </v-col>

          <v-col cols="6" sm="4" md="3">
            <v-btn
              block
              color="primary"
              class="pa-6"
              :loading="loading"
              rounded="lg"
              @click="handleSearch"
            >
              Buscar
            </v-btn>
          </v-col>

          <v-col cols="6" sm="4" md="3">
            <v-btn
              block
              class="pa-6"
              rounded="lg"
              variant="outlined"
              @click="handleClear"
            >
              Limpiar
            </v-btn>
          </v-col>
        </v-row>
      </v-col>

      <v-col cols="12" lg="3">
        <v-btn
          class="pa-6"
          block
          color="primary"
          prepend-icon="mdi-plus"
          rounded="lg"
          @click="openCreateModal"
        >
          Crear usuario
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
      :headers="usersTableHeaders"
      :items="usersTableItems"
      :last-page="lastPage"
      :loading="loading"
      no-data-text="No hay usuarios para mostrar."
      :page="page"
      :per-page="perPage"
      :total="total"
      @update:page="handlePageChange"
      @update:per-page="handlePerPageChange"
    >
      <template #item.estado="{ value }">
        <StatusChip :status="value" />
      </template>

      <template #item.actions="{ item }">
        <div class="users-table__actions">
          <v-tooltip text="Editar">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Editar usuario"
                color="secondary"
                density="comfortable"
                icon="mdi-pencil-box-outline"
                variant="text"
                @click="openEditModal(item)"
              />
            </template>
          </v-tooltip>

          <v-tooltip text="Restablecer contraseña">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Restablecer contraseña"
                color="secondary"
                density="comfortable"
                icon="mdi-key-outline"
                variant="text"
                @click="openResetPasswordModal(item)"
              />
            </template>
          </v-tooltip>

          <v-tooltip
            v-if="!isValidatorRole(item)"
            :text="isActiveStatus(getEstado(item)) ? 'Desactivar' : 'Activar'"
          >
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                :aria-label="
                  isActiveStatus(getEstado(item))
                    ? 'Desactivar usuario'
                    : 'Activar usuario'
                "
                color="secondary"
                density="comfortable"
                :icon="
                  isActiveStatus(getEstado(item))
                    ? 'mdi-close-circle-outline'
                    : 'mdi-check-circle-outline'
                "
                variant="text"
                @click="openToggleStatusModal(item)"
              />
            </template>
          </v-tooltip>

          <!-- <v-tooltip text="Eliminar">
            <template #activator="{ props }">
              <v-btn
                v-bind="props"
                aria-label="Eliminar usuario"
                color="error"
                density="comfortable"
                icon="mdi-trash-can-outline"
                variant="text"
                @click="openDeleteModal(item)"
              />
            </template>
          </v-tooltip> -->
        </div>
      </template>
    </AppDataTable>

    <UserFormModal
      v-model="showFormModal"
      :loading="actionLoading"
      :mode="formMode"
      :roles="assignableRoles"
      :roles-error="rolesError"
      :roles-loading="rolesLoading"
      :user="selectedUser"
      @cancel="closeModals"
      @submit="handleFormSubmit"
    />

    <UserResetPasswordModal
      v-model="showResetPasswordModal"
      :loading="actionLoading"
      :user="selectedUser"
      @cancel="closeModals"
      @confirm="handleResetPassword"
    />

    <UserStatusModal
      v-model="showStatusModal"
      :is-active="selectedUserIsActive"
      :loading="actionLoading"
      :user="selectedUser"
      @cancel="closeModals"
      @confirm="handleToggleStatus"
    />

    <UserDeleteModal
      v-model="showDeleteModal"
      :loading="actionLoading"
      :user="selectedUser"
      @cancel="closeModals"
      @confirm="handleDeleteUser"
    />
  </v-container>
</template>

<style scoped>
.users-view {
  color: rgb(var(--v-theme-primary));
}

.users-table__actions {
  align-items: center;
  display: flex;
  gap: 6px;
  justify-content: start;
  white-space: nowrap;
}
</style>
