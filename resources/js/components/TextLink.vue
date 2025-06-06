<script setup lang="ts">
import { RouterLink } from 'vue-router';

interface Props {
    href: string;
    tabindex?: number;
    as?: string;
}

defineProps<Props>();
</script>

<template>
    <RouterLink
        :to="href"
        :tabindex="tabindex"
        custom
        v-slot="{ navigate }"
    >
        <a
            :href="href"
            @click.prevent="navigate"
            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
        >
            <slot />
        </a>
    </RouterLink>
</template>