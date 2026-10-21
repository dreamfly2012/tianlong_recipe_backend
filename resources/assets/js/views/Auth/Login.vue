<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
      <div class="p-6 sm:p-8">
        <h2 class="text-2xl font-bold text-gray-900 text-center mb-6">登录到您的账户</h2>

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <!-- Email Input -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">邮箱</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
              </div>
              <input
                id="email"
                v-model="form.email"
                type="email"
                class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out"
                :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': errors.email }"
                placeholder="your.email@example.com"
              >
            </div>
            <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email[0] }}</p>
          </div>

          <!-- Password Input -->
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">密码</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
              </div>
              <input
                id="password"
                v-model="form.password"
                type="password"
                class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out"
                :class="{ 'border-red-500 focus:ring-red-500 focus:border-red-500': errors.password }"
                placeholder="••••••••"
              >
            </div>
            <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password[0] }}</p>
          </div>

          <!-- Validation Errors Summary -->
          <div v-if="generalErrors.length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
              <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">登录失败</h3>
                <div class="mt-2 text-sm text-red-700 space-y-1">
                  <p v-for="item in generalErrors" :key="item.key">
                    {{ item.message }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <div>
            <button
              type="submit"
              :disabled="submitting"
              class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out"
              :class="{ 'opacity-75 cursor-not-allowed': submitting }"
            >
              <span v-if="submitting" class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-white border-r-transparent mr-2"></span>
              登录
            </button>
          </div>
        </form>

        <div class="mt-6 text-center">
          <p class="text-sm text-gray-600">
            还没有账户? <a href="/register" class="font-medium text-blue-600 hover:text-blue-500">立即注册</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth'
export default {
  data() {
    return {
      form: {
        email: '',
        password: ''
      },
      errors: {},
      submitting: false
    }
  },
  computed: {
    generalErrors() {
      return Object.entries(this.errors)
        .filter(([key]) => !['email', 'password'].includes(key))
        .map(([key, messages]) => ({
          key,
          message: Array.isArray(messages) ? messages[0] : messages
        }))
    }
  },
  methods: {
    async handleSubmit() {
      this.submitting = true
      this.errors = {}

      try {
        const authStore = useAuthStore()
        await authStore.login(this.form)

        const redirect = this.$route.query.redirect
        this.$router.push(typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/recipes')
      } catch (error) {
        if (error.response?.status === 422) {
          this.errors = error.response.data.errors || {}
        } else {
          this.errors = { general: [error.response?.data?.message || '登录失败，请稍后重试'] }
        }
      } finally {
        this.submitting = false
      }
    }
  }
}
</script>
