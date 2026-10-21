<template>
  <nav class="bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between h-16">
        <div class="flex-shrink-0 flex items-center">
          <router-link to="/" class="text-xl font-bold text-gray-900">
            Recipe Box
          </router-link>
        </div>
        <div class="sm:ml-6 sm:flex sm:items-center space-x-4">
          <router-link 
            v-if="guest"
            to="/login"
            class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            Login
          </router-link>
          <router-link 
            v-if="guest"
            to="/register"
            class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            Register
          </router-link>
          <router-link 
            v-if="auth"
            to="/recipes/create"
            class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            Create Recipe
          </router-link>
          <button
            v-if="auth"
            @click="logout"
            class="px-3 py-2 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            Logout
          </button>
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const authStore = useAuthStore();

const auth = computed(() => authStore.isAuthenticated);
const guest = computed(() => !auth.value);

const logout = async () => {
  try {
    await authStore.logout();
    router.push('/login');
  } catch (error) {
    console.error('Logout failed:', error);
  }
};
</script>
