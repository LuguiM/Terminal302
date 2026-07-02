<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
})

const route = useRoute()

const visibleChildren = computed(() => {
  return (props.item.dependencias ?? []).filter((child) => child.visible)
})

const hasChildren = computed(() => visibleChildren.value.length > 0)
const icon = computed(() => props.item.icono || 'mdi-circle-medium')
const itemRoute = computed(() => props.item.ruta || undefined)
const isActive = computed(() => Boolean(props.item.ruta && route.path === props.item.ruta))
const isSection = computed(() => hasChildren.value && !props.item.ruta)
</script>

<template>
  <div
    v-if="hasChildren"
    class="sidebar-menu-group"
  >
    <div
      v-if="isSection"
      class="sidebar-menu-section"
    >
      {{ item.titulo }}
    </div>

    <v-list-item
      v-else
      class="sidebar-menu-item"
      :class="{ 'sidebar-menu-item--active': isActive }"
      :prepend-icon="icon"
      rounded="0"
      :title="item.titulo"
      :to="itemRoute"
    />

    <div class="sidebar-menu-children">
      <SidebarMenuItem
        v-for="child in visibleChildren"
        :key="child.id"
        :item="child"
      />
    </div>
  </div>

  <v-list-item
    v-else
    class="sidebar-menu-item"
    :class="{ 'sidebar-menu-item--active': isActive }"
    :prepend-icon="icon"
    rounded="0"
    :title="item.titulo"
    :to="itemRoute"
  />
</template>
