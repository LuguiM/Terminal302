<script setup>
import { computed } from 'vue'

const props = defineProps({
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
})

const emit = defineEmits(['update:page', 'update:perPage'])

const pageModel = computed({
  get() {
    return props.page
  },
  set(value) {
    emit('update:page', Number(value))
  },
})

const perPageModel = computed({
  get() {
    return props.perPage
  },
  set(value) {
    emit('update:perPage', Number(value))
  },
})

const paginationLength = computed(() => Math.max(Number(props.lastPage) || 1, 1))
</script>

<template>
  <v-row
    align="center"
    class="mt-4"
    justify="space-between"
  >
    <v-col
      cols="12"
      md="4"
    >
      <div class="text-body-2 text-secondary">
        Total: {{ total }} registros · Página {{ page }} de {{ paginationLength }}
      </div>
    </v-col>

    <v-col
      cols="12"
      md="4"
    >
      <v-pagination
        v-model="pageModel"
        :length="paginationLength"
        density="comfortable"
        rounded="lg"
        total-visible="5"
      />
    </v-col>

    <v-col
      cols="12"
      md="3"
      lg="2"
    >
      <v-select
        v-model="perPageModel"
        :items="perPageOptions"
        density="comfortable"
        hide-details
        label="Por página"
        variant="outlined"
      />
    </v-col>
  </v-row>
</template>
