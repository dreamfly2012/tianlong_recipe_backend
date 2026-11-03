<template>
  <div
    class="image"
    :class="{ 'image--over': isDragover }"
    @dragover.prevent="isDragover = true"
    @dragleave.prevent="isDragover = false"
    @drop.prevent="handleDrop"
  >
    <input
      id="file-upload"
      ref="fileInput"
      type="file"
      accept="image/*"
      class="hidden-input"
      @change="handleUpload"
    >
    <template v-if="modelValue">
      <ImagePreview
        :preview="modelValue"
        @close="handleClose"
      ></ImagePreview>
      <button type="button" class="image__replace" @click="openPicker">更换图片</button>
    </template>
    <label class="image__upload" v-else for="file-upload">
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

defineProps({
  modelValue: {
    type: [String, File],
    default: null
  }
});

const emit = defineEmits(['update:modelValue']);

const fileInput = ref(null);
const isDragover = ref(false);

const setFile = (file) => {
  if (!file || !file.type.startsWith('image/')) {
    return;
  }
  emit('update:modelValue', file);
};

const openPicker = () => {
  fileInput.value?.click();
};

const handleUpload = (e) => {
  setFile(e.target.files && e.target.files[0]);
  // 清空 input，允许再次选择同一个文件
  e.target.value = '';
};

const handleDrop = (e) => {
  isDragover.value = false;
  const files = e.dataTransfer && e.dataTransfer.files;
  setFile(files && files[0]);
  if (fileInput.value) {
    fileInput.value.value = '';
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

.image--over {
  border-color: #4f46e5;
  background-color: #eef2ff;
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

/* 已有图片时的换图入口 */
.image__replace {
  margin-top: 12px;
  padding: 6px 14px;
  font-size: 14px;
  color: #4f46e5;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 6px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.image__replace:hover {
  background: #e0e7ff;
}

/* 悬停效果 */
.image:hover {
  border-color: #4f46e5;
}
</style>
