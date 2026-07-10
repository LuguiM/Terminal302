<script setup>
import { computed } from 'vue'
import { useDisplay } from 'vuetify'

import AppPagination from '@/components/common/AppPagination.vue'

const props = defineProps({
  headers: {
    type: Array,
    default: () => [],
  },
  items: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  page: {
    type: Number,
    default: 1,
  },
  perPage: {
    type: Number,
    default: 15,
  },
  total: {
    type: Number,
    default: 0,
  },
  lastPage: {
    type: Number,
    default: 1,
  },
  perPageOptions: {
    type: Array,
    default: () => [5, 10, 15, 25, 50],
  },
  itemValue: {
    type: String,
    default: 'id',
  },
  noDataText: {
    type: String,
    default: 'No hay datos para mostrar.',
  },
})

defineEmits(['update:page', 'update:perPage'])

const { smAndDown } = useDisplay()

const mobileHeaders = computed(() =>
  props.headers.filter((header) => header.mobile !== false),
)
</script>

<template>
  <div
    v-if="smAndDown"
    class="d-flex flex-column ga-3"
  >
    <v-progress-linear
      v-if="loading"
      color="primary"
      indeterminate
    />

    <v-alert
      v-else-if="items.length === 0"
      class="mb-0"
      color="secondary"
      variant="tonal"
    >
      {{ noDataText }}
    </v-alert>

    <v-card
      v-for="item in items"
      v-else
      :key="item[itemValue]"
      class="app-data-table-mobile__card"
      color="primary"
      rounded="lg"
      variant="outlined"
    >
      <v-card-text class="pa-0">
        <div
          v-for="header in mobileHeaders"
          :key="header.key"
          class="app-data-table-mobile__row pa-4"
        >
          <div class="text-primary text-caption font-weight-black text-uppercase">
            {{ header.title }}
          </div>

          <div
            class="text-body-2 text-high-emphasis"
            :class="{
              'd-flex justify-start': header.key === 'actions',
              'app-data-table-mobile__value': header.key !== 'actions',
            }"
          >
            <slot
              :item="item"
              :name="`item.${header.key}`"
              :value="item[header.key]"
            >
              {{ item[header.key] || '-' }}
            </slot>
          </div>
        </div>
      </v-card-text>
    </v-card>
  </div>

  <v-sheet
    v-else
    border
    class="app-data-table overflow-x-auto"
    rounded="lg"
  >
    <v-data-table
      class="app-data-table__table"
      :headers="headers"
      hide-default-footer
      :items="items"
      :items-per-page="perPage"
      :item-value="itemValue"
      :loading="loading"
      :no-data-text="noDataText"
    >
      <template
        v-for="(_, slotName) in $slots"
        #[slotName]="slotProps"
      >
        <slot
          :name="slotName"
          v-bind="slotProps"
        />
      </template>
    </v-data-table>
  </v-sheet>

  <AppPagination
    :last-page="lastPage"
    :page="page"
    :per-page="perPage"
    :per-page-options="perPageOptions"
    :total="total"
    @update:page="$emit('update:page', $event)"
    @update:per-page="$emit('update:perPage', $event)"
  />
</template>

<style scoped>
.app-data-table {
  border-color: rgb(var(--v-theme-primary)) !important;
}

.app-data-table__table {
  min-width: 980px;
}

.app-data-table__table :deep(table) {
  border-collapse: collapse;
}

.app-data-table__table :deep(thead th) {
  color: rgb(var(--v-theme-primary));
  font-size: 1.2rem;
  font-weight: 900;
  height: 76px;
  white-space: nowrap;
}

.app-data-table__table :deep(tbody td) {
  border-top: 1px solid rgb(var(--v-theme-primary));
  color: #111111;
  font-size: 1rem;
  height: 76px;
  vertical-align: middle;
}

.app-data-table__table :deep(tbody tr:nth-child(odd)) {
  background: rgb(var(--v-theme-greyLigth));
}

.app-data-table__table :deep(.v-data-table__tr:hover) {
  background: transparent;
}

.app-data-table-mobile__card {
  border-color: rgb(var(--v-theme-primary));
}

.app-data-table-mobile__row + .app-data-table-mobile__row {
  border-top: 1px solid rgba(var(--v-theme-primary), 0.16);
}

.app-data-table-mobile__value {
  overflow-wrap: anywhere;
}
</style>
