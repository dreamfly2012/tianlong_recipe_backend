<template>
  <div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <!-- 加载状态 -->
    <div v-if="!recipe.id && !error" class="flex justify-center items-center min-h-[400px]">
      <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
    </div>

    <!-- 错误状态 -->
    <div v-if="error" class="max-w-4xl mx-auto bg-red-50 border border-red-200 rounded-lg p-6 text-red-700 mb-8">
      <p>{{ error }}</p>
      <button @click="fetchRecipe" class="mt-4 text-sm text-blue-600 hover:text-blue-800">重试</button>
    </div>

    <!-- 食谱内容 -->
    <div v-if="recipe.id" class="max-w-6xl mx-auto">
      <!-- 标题栏 -->
      <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center">
        <router-link :to="{ name: 'recipes' }" class="text-blue-600 hover:text-blue-800 mb-4 sm:mb-0 flex items-center">
          <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
          </svg>
          返回列表
        </router-link>
        <div v-if="authStore.isAuthenticated && (authStore.user.id === recipe.user_id || authStore.user.is_admin)" class="flex space-x-3">
          <router-link :to="`/recipes/${recipe.id}/edit`" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-sm transition duration-200 flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.536L16.732 3.732z"></path>
            </svg>
            编辑
          </router-link>
          <button @click="remove" :disabled="isRemoving" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg shadow-sm transition duration-200 flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
            {{ isRemoving ? '删除中...' : '删除' }}
          </button>
        </div>
      </div>

      <!-- 食谱头部 -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8 transform transition duration-300 hover:shadow-lg">
        <div class="md:flex">
          <!-- 食谱图片 -->
          <div class="md:w-1/3 bg-gray-100">
            <img
              :src="recipe.image_src || (recipe.image ? `/images/${recipe.image}` : '/images/placeholder.jpg')"
              :alt="recipe.name"
              class="w-full h-full object-cover"
              :class="!recipe.image ? 'opacity-50' : ''"
            >
          </div>

          <!-- 食谱信息 -->
          <div class="p-6 md:p-8 md:w-2/3">
            <div class="flex items-center text-sm text-gray-500 mb-3">
              <span>作者: {{ recipe.user?.name || 'Unknown' }}</span>
              <span class="mx-2">•</span>
              <span>分类: {{ recipe.category?.name || '未分类' }}</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">{{ recipe.name }}</h1>
            <div class="prose prose-blue max-w-none text-gray-700">
              <p>{{ recipe.description }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- 材料和步骤 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- 材料列表 -->
        <div class="bg-white rounded-xl shadow-md p-6 transform transition duration-300 hover:shadow-lg">
          <h2 class="text-2xl font-semibold text-gray-900 mb-4 flex items-center">
            <svg class="w-6 h-6 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
            </svg>
            材料准备
          </h2>
          <ul class="space-y-3 mt-6">
            <li v-for="(ingredient, index) in recipe.ingredients" :key="index" class="flex justify-between items-center py-3 border-b border-gray-100 last:border-0">
              <span class="font-medium text-gray-800">{{ ingredient.name }}</span>
              <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">{{ ingredient.qty }}</span>
            </li>
          </ul>
        </div>

        <!-- 步骤说明 -->
        <div class="bg-white rounded-xl shadow-md p-6 transform transition duration-300 hover:shadow-lg">
          <h2 class="text-2xl font-semibold text-gray-900 mb-4 flex items-center">
            <svg class="w-6 h-6 text-amber-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            烹饪步骤
          </h2>
          <ol class="space-y-6 mt-6">
            <li v-for="(direction, i) in recipe.directions" :key="i" class="flex">
              <div class="flex-shrink-0 h-8 w-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-medium mr-4 mt-0.5">
                {{ i + 1 }}
              </div>
              <p class="text-gray-700 leading-relaxed">{{ direction.description }}</p>
            </li>
          </ol>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useFlash } from '@/composables/useFlash';
import api from '@/helpers/api';

const { showSuccess, showError } = useFlash();

// 响应式状态
const recipe = ref({ id: null, user: {}, ingredients: [], directions: [], category: {} });
const isRemoving = ref(false);
const error = ref(null);
const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

// 获取食谱详情
const fetchRecipe = async () => {
  error.value = null;
  try {
    const response = await api.get(`/recipes/${route.params.id}`);
    recipe.value = response.data.data || {};
  } catch (err) {
    console.error('Failed to fetch recipe:', err);
    error.value = err.response?.data?.message || '无法加载食谱详情，请稍后重试';

    if (err.response?.status === 404) {
      router.push({ name: 'not-found' });
    }
  }
};

// 删除食谱
const remove = async () => {
  if (!confirm('确定要删除这个食谱吗？此操作无法撤销。')) return;

  try {
    isRemoving.value = true;
    const response = await api.delete(`/recipes/${route.params.id}`);

    if (response.data.code === 0) {
      showSuccess(response.data.message || '食谱删除成功！');
      router.push('/recipes');
    }
  } catch (err) {
    console.error('Failed to delete recipe:', err);
    showError(err.response?.data?.message || '删除失败，请重试');
  } finally {
    isRemoving.value = false;
  }
};

onMounted(fetchRecipe);
</script>