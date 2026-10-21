<template>
  <div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <!-- 加载状态 -->
    <div v-if="isLoading" class="flex justify-center items-center h-64">
      <svg class="animate-spin h-10 w-10 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <div v-else class="max-w-6xl mx-auto bg-white rounded-xl shadow-md overflow-hidden">
      <!-- 表单头部 -->
      <div class="bg-gray-50 border-b border-gray-200 px-6 py-4 flex justify-between items-center">
        <h3 class="text-xl font-semibold text-gray-900">{{ action }} 菜谱</h3>
        <div class="flex space-x-3">
          <button 
            @click="save" 
            :disabled="isProcessing"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition duration-200 flex items-center justify-center"
            :class="isProcessing ? 'opacity-75 cursor-not-allowed' : ''"
          >
            <svg v-if="isProcessing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            保存
          </button>
          <button 
            @click="router.back()"
            :disabled="isProcessing"
            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-lg shadow-sm transition duration-200"
            :class="isProcessing ? 'opacity-75 cursor-not-allowed' : ''"
          >
            取消
          </button>
        </div>
      </div>

      <!-- 表单内容 -->
      <div class="p-6">
        <!-- 图片上传和基本信息 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
          <!-- 图片上传 -->
          <div class="md:col-span-1">
            <div class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:bg-gray-100 transition duration-200">
              <ImageUpload v-model="form.image" :show-preview="true"></ImageUpload>
              <p class="mt-2 text-sm text-gray-500">点击上传或拖放图片</p>
              <small class="block mt-2 text-red-500" v-if="error.image">{{ error.image[0] }}</small>
            </div>
          </div>

          <!-- 基本信息 -->
          <div class="md:col-span-2 space-y-6">
            <!-- 菜名 -->
            <div>
              <label for="name" class="block text-sm font-medium text-gray-700 mb-1">菜名</label>
              <input
                id="name"
                type="text"
                v-model="form.name"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                :class="error.name ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : ''"
              >
              <small class="block mt-1 text-red-500" v-if="error.name">{{ error.name[0] }}</small>
            </div>

            <!-- 分类 -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">分类</label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              
                <select
                  v-model="form.category_id"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                  :class="error.category_id ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : ''"
                >
                  <option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
              </div>
              <small class="block mt-1 text-red-500" v-if="error.category_id">{{ error.category_id[0] }}</small>
            </div>

            <!-- 描述 -->
            <div>
              <label for="description" class="block text-sm font-medium text-gray-700 mb-1">描述</label>
              <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                :class="error.description ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : ''"
              ></textarea>
              <small class="block mt-1 text-red-500" v-if="error.description">{{ error.description[0] }}</small>
            </div>
          </div>
        </div>

        <!-- 材料和步骤 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- 材料列表 -->
          <div class="bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
              </svg>
              材料
            </h3>
              <!-- 添加整体错误提示 -->
  <small class="block mt-1 text-red-500 mb-4" v-if="error.ingredients">{{ error.ingredients[0] }}</small>
            <div class="space-y-4">
              <div v-for="(ingredient, index) in form.ingredients" :key="index" class="flex items-center space-x-3">
                <input
                  type="text"
                  v-model="ingredient.name"
                  placeholder="材料名称"
                  class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                  :class="error[`ingredients.${index}.name`] ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : ''"
                >
                <input
                  type="text"
                  v-model="ingredient.qty"
                  placeholder="用量"
                  class="w-32 px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                  :class="error[`ingredients.${index}.qty`] ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : ''"
                >
                <button
                  @click="remove('ingredients', index)"
                  class="p-2 bg-red-100 hover:bg-red-200 text-red-600 rounded-lg transition duration-200"
                  :disabled="form.ingredients.length <= 1"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                  </svg>
                </button>
              </div>
            </div>

            <button
              @click="addIngredient"
              class="mt-4 px-4 py-2 bg-green-50 hover:bg-green-100 text-green-600 font-medium rounded-lg transition duration-200 flex items-center"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"></path>
              </svg>
              添加材料
            </button>
          </div>

          <!-- 步骤说明 -->
          <div class="bg-gray-50 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
              <svg class="w-5 h-5 text-amber-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
              </svg>
              步骤
            </h3>
              <!-- 添加整体错误提示 -->
           <small class="block mt-1 text-red-500 mb-4" v-if="error.directions">{{ error.directions[0] }}</small>
            <div class="space-y-4">
              <div v-for="(direction, index) in form.directions" :key="index" class="flex items-start space-x-3">
                <div class="flex-shrink-0 h-6 w-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-medium mt-1.5">
                  {{ index + 1 }}
                </div>
                <textarea
                  v-model="direction.description"
                  placeholder="请输入烹饪步骤"
                  rows="2"
                  class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-200"
                  :class="error[`directions.${index}.description`] ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : ''"
                ></textarea>
                <button
                  @click="remove('directions', index)"
                  class="p-2 bg-red-100 hover:bg-red-200 text-red-600 rounded-lg transition duration-200 mt-1"
                  :disabled="form.directions.length <= 1"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                  </svg>
                </button>
              </div>
            </div>

            <button
              @click="addDirection"
              class="mt-4 px-4 py-2 bg-green-50 hover:bg-green-100 text-green-600 font-medium rounded-lg transition duration-200 flex items-center"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"></path>
              </svg>
              添加步骤
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toMultipartedForm } from '@/helpers/form';
import ImageUpload from '@/components/ImageUpload.vue';
import api from '@/helpers/api';
import { useFlash } from '@/composables/useFlash';

const route = useRoute();
const router = useRouter();
const { showSuccess, showError } = useFlash();

const form = reactive({
  name: '',
  description: '',
  image: null,
  category_id: null,
  ingredients: [],
  directions: []
});

const categories = ref([]);
const error = ref({});
const isProcessing = ref(false);
const isLoading = ref(true);

const mode = route.meta.mode === 'edit' ? 'edit' : 'create';
const action = ref(mode === 'edit' ? '更新' : '创建');
const initializeURL = ref(mode === 'edit' ? `/recipes/${route.params.id}/edit` : '/recipe.create');
const storeURL = ref(mode === 'edit' ? `/recipes/${route.params.id}` : '/recipes');

onMounted(async () => {
  try {
    const { data } = await api.get(initializeURL.value);
    const payload = data.data || {};

    Object.assign(form, payload.form || {});
    categories.value = payload.categories || [];

    if (!Array.isArray(form.ingredients) || form.ingredients.length === 0) {
      form.ingredients = [{ name: '', qty: '' }];
    }

    if (!Array.isArray(form.directions) || form.directions.length === 0) {
      form.directions = [{ description: '' }];
    }

    form.image = form.image || null;
    form.category_id = form.category_id || categories.value[0]?.id || null;
  } catch (err) {
    console.error('初始化表单失败:', err);
    showError('页面加载失败，请重试');
    router.push('/recipes');
  } finally {
    isLoading.value = false;
  }
});

const save = async () => {
  isProcessing.value = true;
  error.value = {};

  if (!form.name.trim()) {
    error.value.name = ['菜名不能为空'];
    isProcessing.value = false;
    return;
  }

  if (form.ingredients.length === 0) {
    error.value.ingredients = ['请添加至少一种材料'];
    isProcessing.value = false;
    return;
  }

  if (form.ingredients.some(item => !item.name || !item.qty)) {
    error.value.ingredients = ['材料名称和用量不能为空'];
    isProcessing.value = false;
    return;
  }

  if (form.directions.length === 0) {
    error.value.directions = ['请添加至少一个步骤'];
    isProcessing.value = false;
    return;
  }

  if (form.directions.some(item => !item.description)) {
    error.value.directions = ['步骤描述不能为空'];
    isProcessing.value = false;
    return;
  }

  if (!form.category_id) {
    error.value.category_id = ['请选择分类'];
    isProcessing.value = false;
    return;
  }

  if (!form.image && mode !== 'edit') {
    error.value.image = ['请上传图片'];
    isProcessing.value = false;
    return;
  }

  try {
    const payload = toMultipartedForm(form, mode);

    if (mode === 'edit') {
      payload.append('_method', 'PUT');
    }

    const { data } = await api.post(storeURL.value, payload);

    if (data.code === 0) {
      showSuccess(data.message || (mode === 'edit' ? '更新成功' : '创建成功'));
      router.push(`/recipes/${data.data.id}`);
    }
  } catch (err) {
    console.error('保存失败:', err);

    if (err.response?.status === 422) {
      error.value = err.response.data.errors || {};
    } else {
      showError(err.response?.data?.message || '操作失败，请重试');
    }
  } finally {
    isProcessing.value = false;
  }
};

const addDirection = () => {
  form.directions.push({ description: '' });
};

const addIngredient = () => {
  form.ingredients.push({ name: '', qty: '' });
};

const remove = (type, index) => {
  if (form[type].length > 1) {
    form[type].splice(index, 1);
  }
};
</script>
