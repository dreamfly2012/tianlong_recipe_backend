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

    <!-- 食谱列表 - 修改响应式网格配置 -->
    <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <div v-for="recipe in recipes" :key="recipe.id" class="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-lg transition-shadow duration-300 transform hover:-translate-y-1">
        <router-link :to="`/recipes/${recipe.id}`" class="block">
          <img 
            :src="`/images/${recipe.image}`" 
            alt="{{ recipe.name }}" 
            class="w-full h-48 object-cover"
            v-if="recipe.image"
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/helpers/api';

// 定义响应式数据
const recipes = ref([]);
const isLoading = ref(false);
const error = ref(null);

// 数据获取函数
const fetchRecipes = async () => {
  try {
    isLoading.value = true;
    const response = await api.get('/recipes');
    recipes.value = response.data.data; 
    error.value = null;
  } catch (err) {
    error.value = 'Failed to fetch recipes. Please try again later.';
    console.error(err);
  } finally {
    isLoading.value = false;
  }
};

// 在组件挂载时获取数据
onMounted(fetchRecipes);
</script>