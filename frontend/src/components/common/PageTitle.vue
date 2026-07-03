<script setup>
defineProps({
  title: {
    type: String,
    default: "",
  },
})
</script>

<template>
  <h1 class="page-title mt-0 mb-11">
    <slot>{{ title }}</slot>
  </h1>
</template>

<style scoped>
.page-title {
  color: rgb(var(--v-theme-primary));
  font-size: clamp(2rem, 5vw, 2.6rem);
  font-weight: 900;
  line-height: 1.15;
  text-align: center;
}
</style>
