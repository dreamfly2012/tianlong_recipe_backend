<script setup lang="ts">
import { useRouter } from 'vue-router';

const router = useRouter();

const goBack = () => {
    router.back();
};

const goHome = () => {
    router.push({ name: 'welcome' });
};
</script>

<template>
    <div class="h-full flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-9xl font-bold text-gray-800">404</h1>
            <h2 class="text-2xl font-semibold text-gray-600 mt-4">页面未找到</h2>
            <p class="text-gray-500 mt-2">抱歉，您请求的页面不存在。</p>
            <div class="mt-8 space-x-4">
                <button
                    @click="goBack"
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors"
                >
                    返回上一页
                </button>
                <button
                    @click="goHome"
                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors"
                >
                    返回首页
                </button>
            </div>
        </div>
    </div>
</template>