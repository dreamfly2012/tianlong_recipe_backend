<script setup lang="ts">
import { RouterView, useRoute } from 'vue-router';
import { computed } from 'vue';
import AppShell from './components/AppShell.vue';

const route = useRoute();
const variant = computed(() => route.meta.variant as 'header' | 'sidebar' | undefined);
</script>

<template>
  <AppShell :variant="variant">
    <RouterView />
  </AppShell>
</template>