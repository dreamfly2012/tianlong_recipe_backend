<template>
  <Transition name="fade">
    <div 
      v-if="flash.show"
      :class="[
        'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm w-full',
        {
          'bg-green-100 text-green-800': flash.type === 'success',
          'bg-red-100 text-red-800': flash.type === 'error',
          'bg-blue-100 text-blue-800': flash.type === 'info',
          'bg-yellow-100 text-yellow-800': flash.type === 'warning'
        }
      ]"
    >
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <component 
            :is="iconComponent" 
            class="h-5 w-5" 
            aria-hidden="true"
          />
        </div>
        <div class="ml-3 flex-1">
          <p class="text-sm font-medium">
            {{ flash.message }}
          </p>
        </div>
        <div class="ml-4 flex-shrink-0 flex">
          <button
            @click="hideMessage"
            class="inline-flex rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2"
            :class="{
              'text-green-400 hover:text-green-500 focus:ring-green-500': flash.type === 'success',
              'text-red-400 hover:text-red-500 focus:ring-red-500': flash.type === 'error',
              'text-blue-400 hover:text-blue-500 focus:ring-blue-500': flash.type === 'info',
              'text-yellow-400 hover:text-yellow-500 focus:ring-yellow-500': flash.type === 'warning'
            }"
          >
            <span class="sr-only">Close</span>
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CheckCircleIcon, XCircleIcon, InformationCircleIcon, ExclamationTriangleIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { useFlash } from '@/composables/useFlash';

const { flash, hideMessage } = useFlash();

const iconComponent = computed(() => {
  switch (flash.value.type) {
    case 'success':
      return CheckCircleIcon;
    case 'error':
      return XCircleIcon;
    case 'info':
      return InformationCircleIcon;
    case 'warning':
      return ExclamationTriangleIcon;
    default:
      return null;
  }
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: all 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
