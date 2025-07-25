<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-50 flex items-center justify-center p-4 sm:p-6 lg:p-8">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden">
      <div class="p-6 sm:p-8 md:p-10">
        <form class="space-y-6" @submit.prevent="register">
          <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900">Create an Account</h1>
            <p class="mt-2 text-sm text-gray-500">Get started with your free account</p>
          </div>

          <div class="space-y-2">
            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
            <input
              id="name"
              type="text"
              v-model="form.name"
              class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
              placeholder="John Doe"
            >
            <small class="text-red-500 text-sm" v-if="error.name">{{ error.name[0] }}</small>
          </div>

          <div class="space-y-2">
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input
              id="email"
              type="email"
              v-model="form.email"
              class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
              placeholder="you@example.com"
            >
            <small class="text-red-500 text-sm" v-if="error.email">{{ error.email[0] }}</small>
          </div>

          <div class="space-y-2">
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input
              id="password"
              type="password"
              v-model="form.password"
              class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
              placeholder="••••••••"
            >
            <small class="text-red-500 text-sm" v-if="error.password">{{ error.password[0] }}</small>
          </div>

          <div class="space-y-2">
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input
              id="password_confirmation"
              type="password"
              v-model="form.password_confirmation"
              class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200"
              placeholder="••••••••"
            >
          </div>

          <div>
            <button
              :disabled="isProcessing"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg shadow-md transition duration-300 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-70 disabled:cursor-not-allowed"
            >
              <span v-if="!isProcessing">Register</span>
              <span v-if="isProcessing">Creating account...</span>
            </button>
          </div>
        </form>
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
                    name: '',
                    email: '',
                    password: '',
                    password_confirmation: ''
                },
                error: {},
                isProcessing: false
            }
        },
        methods: {
            async register() {
              this.submitting = true
              this.errors = {}

              try {
                // 移除组件内直接API调用
                const authStore = useAuthStore()
                await authStore.register(this.form)
                
                // 不需要手动跳转，store的register方法已包含router.push('/login')
              } catch (error) {
                if (error.response && error.response.status === 422) {
                  this.errors = error.response.data.errors || {}
                } else {
                  this.errors = { general: '注册失败，请检查您的信息或稍后再试。' }
                }
              } finally {
                this.submitting = false
              }
            }
        }
    }
</script>
