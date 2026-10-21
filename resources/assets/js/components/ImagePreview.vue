<template>
  <div class="image__preview" v-if="image">
    <img :src="image" class="preview-img">
    <button class="btn btn__danger image__close" @click="close">
      &times;
    </button>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
  preview: {
    type: [String, File],
    required: true,
    default: null
  }
});

const emit = defineEmits(['close']);
const image = ref(null);

const setPreview = () => {
  if (props.preview instanceof File) {
    const fileReader = new FileReader();
    fileReader.onload = (event) => {
      image.value = event.target.result;
    };
    fileReader.readAsDataURL(props.preview);
  } else if (typeof props.preview === 'string') {
    image.value = props.preview.startsWith('http') || props.preview.startsWith('/')
      ? props.preview
      : `/images/${props.preview}`;
  } else {
    image.value = null;
  }
};

// 初始化预览
onMounted(setPreview);

// 监听preview prop变化
watch(
  () => props.preview,
  () => setPreview()
);

const close = () => {
  emit('close');
};
</script>

<style scoped>
.preview-img {
  width: 100%;
  height: 300px; /* 固定高度 */
  object-fit: cover; /* 覆盖容器，保持比例，可能裁剪部分图片 */
  object-position: center; /* 居中显示 */
  border-radius: 4px;
}
.image__preview {
  position: relative;
}
.image__close {
  position: absolute;
  top: 8px;
  right: 8px;
}
</style>
