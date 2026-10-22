<script setup lang="ts">
import { computed } from 'vue'
import type { Court } from '../../types/court'
import { getCourtGalleryImages } from '@/lib/courtImage'

const props = defineProps<{
  show: boolean
  court: Court
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const galleryImages = computed(() => getCourtGalleryImages(props.court))
</script>

<template>
  <div v-if="show" class="modal-backdrop gallery-modal" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <h3 class="modal-title">📷 Thư Viện Hình Ảnh: {{ court.code }}</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="gallery-grid">
          <img
            v-for="(img, idx) in galleryImages"
            :key="idx"
            :src="img"
            :alt="`${court.code} - Ảnh ${idx + 1}`"
            class="gallery-item-img"
          />
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="emit('close')">Đóng</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 12px;
}

.gallery-item-img {
  width: 100%;
  height: 180px;
  object-fit: cover;
  border-radius: 10px;
}

.btn-cancel {
  padding: 8px 18px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  cursor: pointer;
}
</style>
