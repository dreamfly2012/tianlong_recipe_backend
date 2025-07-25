<template>
  <div class="image">
    <ImagePreview
      :preview="modelValue"
      @close="handleClose"
      v-if="modelValue"
    ></ImagePreview>
    <!-- 修改上传区域为可点击的标签 -->
    <label class="image__upload" v-else for="file-upload">
      <input
        id="file-upload"
        type="file"
        accept="image/*"
        @change="handleUpload"
        class="hidden-input"
      >
      <div class="upload-prompt">
        <svg class="w-10 h-10 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
        </svg>
        <p class="text-gray-500">点击上传或拖放图片</p>
      </div>
    </label>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import ImagePreview from './ImagePreview.vue';

const props = defineProps({
  modelValue: {
    type: [String, File],
    default: null
  }
});

const emit = defineEmits(['update:modelValue']);

const handleUpload = (e) => {
  const files = e.target.files;
  if (files && files.length > 0) {
    emit('update:modelValue', files[0]);
    // 清除input值，允许重复选择相同文件
    e.target.value = '';
  }
};

const handleClose = () => {
  emit('update:modelValue', null);
};
</script>

<style scoped>
.image {
  position: relative;
  width: 100%;
  border: 2px dashed #ccc;
  border-radius: 8px;
  padding: 20px;
  text-align: center;
  transition: border-color 0.3s;
}

/* 上传区域样式 */
.image__upload {
  cursor: pointer;
  min-height: 150px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 100%;
}

/* 隐藏原生输入框 */
.hidden-input {
  display: none;
}

/* 上传提示文字样式 */
.upload-prompt {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

/* 悬停效果 */
.image:hover {
  border-color: #4f46e5;
}
</style>
