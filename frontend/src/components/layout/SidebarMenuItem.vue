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
</script>

<template>
  <v-list-group
    v-if="hasChildren"
    :value="item.id"
  >
    <template #activator="{ props: activatorProps }">
      <v-list-item
        v-bind="activatorProps"
        class="sidebar-menu-item"
        :prepend-icon="icon"
        rounded="0"
        :title="item.titulo"
      />
    </template>

    <SidebarMenuItem
      v-for="child in visibleChildren"
      :key="child.id"
      :item="child"
    />
  </v-list-group>

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
