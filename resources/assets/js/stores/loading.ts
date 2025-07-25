import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useLoadingStore = defineStore('loading', () => {
  const isLoading = ref(false);
  const message = ref('');
  let loadingCount = 0;

  const startLoading = (loadingMessage = '') => {
    loadingCount++;
    isLoading.value = true;
    if (loadingMessage) {
      message.value = loadingMessage;
    }
  };

  const stopLoading = () => {
    loadingCount = Math.max(0, loadingCount - 1);
    if (loadingCount === 0) {
      isLoading.value = false;
      message.value = '';
    }
  };

  const resetLoading = () => {
    loadingCount = 0;
    isLoading.value = false;
    message.value = '';
  };

  const withLoading = async <T>(promise: Promise<T>, loadingMessage = ''): Promise<T> => {
    startLoading(loadingMessage);
    try {
      return await promise;
    } finally {
      stopLoading();
    }
  };

  return {
    isLoading: computed(() => isLoading.value),
    message: computed(() => message.value),
    startLoading,
    stopLoading,
    resetLoading,
    withLoading
  };
});
