<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-50 flex items-center justify-center p-4 sm:p-6 lg:p-8">
    <div class="home w-full max-w-3xl bg-white rounded-2xl shadow-xl overflow-hidden">
      <div class="p-6 sm:p-8 md:p-10">
        <h1 class="text-4xl font-bold text-gray-800 mb-3 tracking-tight">Welcome to Recipe Box</h1>
        <p class="text-lg text-gray-600 mb-8 leading-relaxed">A place to store and share your favorite recipes with friends and family.</p>
        
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <router-link 
            v-if="!authStore.isAuthenticated"
            to="/login"
            class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-3 rounded-lg shadow-md transition duration-300 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
          >
            Login
          </router-link>
          
          <router-link 
            v-if="!authStore.isAuthenticated"
            to="/register"
            class="bg-green-600 hover:bg-green-700 text-white font-medium px-6 py-3 rounded-lg shadow-md transition duration-300 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
          >
            Register
          </router-link>
          
          <router-link 
            v-if="authStore.isAuthenticated"
            to="/recipes"
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-3 rounded-lg shadow-md transition duration-300 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          >
            View Recipes
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
</script>

<style scoped>
/* 移除原有样式定义，改用Tailwind工具类 */
</style>
