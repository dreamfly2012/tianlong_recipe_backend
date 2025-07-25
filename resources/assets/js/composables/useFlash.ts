import { ref, computed } from 'vue';

export const useFlash = () => {
  const flash = ref<{
    type: 'success' | 'error' | 'info' | 'warning' | null;
    message: string;
    show: boolean;
  }>({
    type: null,
    message: '',
    show: false
  });

  const showMessage = (type: 'success' | 'error' | 'info' | 'warning', message: string, duration = 5000) => {
    flash.value = {
      type,
      message,
      show: true
    };

    if (duration > 0) {
      setTimeout(() => {
        hideMessage();
      }, duration);
    }
  };

  const hideMessage = () => {
    flash.value.show = false;
    // Small delay before clearing the message to allow for fade-out animation
    setTimeout(() => {
      flash.value.message = '';
      flash.value.type = null;
    }, 300);
  };

  const showSuccess = (message: string, duration?: number) => {
    showMessage('success', message, duration);
  };

  const showError = (message: string, duration?: number) => {
    showMessage('error', message, duration);
  };

  const showInfo = (message: string, duration?: number) => {
    showMessage('info', message, duration);
  };

  const showWarning = (message: string, duration?: number) => {
    showMessage('warning', message, duration);
  };

  return {
    flash: computed(() => flash.value),
    showMessage,
    hideMessage,
    showSuccess,
    showError,
    showInfo,
    showWarning
  };
};
