<script setup lang="ts">
import { SidebarProvider } from '@/components/ui/sidebar';

interface Props {
    variant?: 'header' | 'sidebar';
    sidebarOpen?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    sidebarOpen: false
});
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <SidebarProvider v-else :default-open="sidebarOpen">
        <slot />
    </SidebarProvider>
</template>