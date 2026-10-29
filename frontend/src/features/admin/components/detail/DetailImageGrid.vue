<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{ images?: string[] | null; alt: string }>()

const { t } = useI18n()
</script>

<template>
  <div v-if="images?.length" class="detail-images">
    <a
      v-for="(src, index) in images"
      :key="src"
      :href="src"
      target="_blank"
      rel="noopener noreferrer"
      class="detail-image"
    >
      <img :src="src" :alt="`${alt} ${index + 1}`" loading="lazy" />
    </a>
  </div>
  <p v-else class="detail-empty">{{ t('admin.venueDetail.noImages') }}</p>
</template>

<style scoped>
.detail-images {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(140px, 100%), 1fr));
  gap: 10px;
}

.detail-image {
  display: block;
  aspect-ratio: 4 / 3;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--outline);
}

.detail-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.detail-empty {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}
</style>
