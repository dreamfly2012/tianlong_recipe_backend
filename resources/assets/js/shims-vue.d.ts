/* eslint-disable */
declare module '*.vue' {
  import type { DefineComponent } from 'vue';
  const component: DefineComponent<{}, {}, any>;
  export default component;
}

declare module '@/stores/*' {
  import type { DefineStore } from 'pinia';
  const store: ReturnType<DefineStore>;
  export default store;
}

declare module '@/composables/*' {
  const composable: any;
  export default composable;
}

declare module '@/helpers/*' {
  const helper: any;
  export default helper;
}

declare module '@heroicons/vue/24/outline' {
  import { Component } from 'vue';
  export const XMarkIcon: Component;
  export const CheckCircleIcon: Component;
  export const XCircleIcon: Component;
  export const InformationCircleIcon: Component;
  export const ExclamationTriangleIcon: Component;
  // 添加其他需要的图标
}
