<template>
  <div class="container mx-auto px-4 py-8">
    <!-- 加载状态 -->
    <div v-if="isLoading" class="flex justify-center items-center h-64">
      <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div>
    </div>

    <!-- 错误提示 -->
    <div v-else-if="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
      <span class="block sm:inline">{{ error }}</span>
    </div>

    <template v-else>
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">全部食谱</h1>
        <router-link
          to="/recipes/create"
          class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-sm transition duration-200"
        >
          创建食谱
        </router-link>
      </div>

      <!-- 食谱列表 -->
      <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <div
          v-for="recipe in recipes"
          :key="recipe.id"
          class="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-lg transition-shadow duration-300 transform hover:-translate-y-1"
        >
          <router-link :to="`/recipes/${recipe.id}`" class="block">
            <img
              v-if="recipe.image"
              :src="recipe.image_src || `/images/${recipe.image}`"
              :alt="recipe.name"
              class="w-full h-48 object-cover"
            >
            <div class="p-4">
              <p class="text-gray-800 font-medium text-center truncate">{{ recipe.name }}</p>
            </div>
          </router-link>
        </div>

        <!-- 空状态 -->
        <div v-if="recipes.length === 0" class="col-span-full text-center py-12">
          <p class="text-gray-500">暂无食谱数据</p>
        </div>
      </div>

      <!-- 分页 -->
      <div v-if="lastPage > 1" class="flex justify-center items-center gap-4 mt-8">
        <button
          class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="currentPage <= 1"
          @click="goToPage(currentPage - 1)"
        >
          上一页
        </button>
        <span class="text-gray-600">第 {{ currentPage }} / {{ lastPage }} 页</span>
        <button
          class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="currentPage >= lastPage"
          @click="goToPage(currentPage + 1)"
        >
          下一页
        </button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/helpers/api';

const recipes = ref([]);
const isLoading = ref(false);
const error = ref(null);
const currentPage = ref(1);
const lastPage = ref(1);

const fetchRecipes = async (page = 1) => {
  try {
    isLoading.value = true;
    const response = await api.get('/recipes', { params: { page } });
    const paginator = response.data.data;

    recipes.value = paginator.data || [];
    currentPage.value = paginator.current_page || 1;
    lastPage.value = paginator.last_page || 1;
    error.value = null;
  } catch (err) {
    error.value = '加载食谱失败，请稍后重试。';
    console.error('Failed to fetch recipes:', err);
  } finally {
    isLoading.value = false;
  }
};

const goToPage = (page) => {
  if (page < 1 || page > lastPage.value) {
    return;
  }

  fetchRecipes(page);
};

onMounted(() => fetchRecipes());
</script>
