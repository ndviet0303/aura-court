<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{ images: string[] }>()
const { t } = useI18n()
</script>

<template>
  <div v-if="images.length" class="image-grid">
    <a
      v-for="(url, index) in images"
      :key="url"
      :href="url"
      target="_blank"
      rel="noopener"
      class="image-link"
    >
      <img :src="url" :alt="t('community.images.alt', { n: index + 1 })" loading="lazy" />
    </a>
  </div>
</template>

<style scoped>
.image-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(96px, 100%), 1fr));
  gap: 6px;
}

.image-link {
  display: block;
  aspect-ratio: 1;
  overflow: hidden;
  border-radius: 10px;
  border: 1px solid var(--outline);
}

.image-link img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
