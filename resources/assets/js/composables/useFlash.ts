import { computed, ref } from 'vue';

type FlashType = 'success' | 'error' | 'info' | 'warning';

interface FlashState {
  type: FlashType | null;
  message: string;
  show: boolean;
}

// 模块级单例：任何组件调用 useFlash 都共享同一份提示状态
const flash = ref<FlashState>({
  type: null,
  message: '',
  show: false,
});

let hideTimer: ReturnType<typeof setTimeout> | null = null;

const hideMessage = () => {
  flash.value.show = false;

  if (hideTimer) {
    clearTimeout(hideTimer);
  }

  // 等淡出动画结束后再清空内容
  hideTimer = setTimeout(() => {
    flash.value.message = '';
    flash.value.type = null;
    hideTimer = null;
  }, 300);
};

const showMessage = (type: FlashType, message: string, duration = 5000) => {
  if (hideTimer) {
    clearTimeout(hideTimer);
    hideTimer = null;
  }

  flash.value = {
    type,
    message,
    show: true,
  };

  if (duration > 0) {
    hideTimer = setTimeout(() => {
      hideMessage();
    }, duration);
  }
};

const showSuccess = (message: string, duration?: number) => showMessage('success', message, duration);
const showError = (message: string, duration?: number) => showMessage('error', message, duration);
const showInfo = (message: string, duration?: number) => showMessage('info', message, duration);
const showWarning = (message: string, duration?: number) => showMessage('warning', message, duration);

export const useFlash = () => ({
  flash: computed(() => flash.value),
  showMessage,
  hideMessage,
  showSuccess,
  showError,
  showInfo,
  showWarning,
});
