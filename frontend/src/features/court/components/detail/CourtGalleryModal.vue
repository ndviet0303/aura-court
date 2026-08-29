<script setup lang="ts">
import type { Court } from '../../types/court'

defineProps<{
  show: boolean
  court: Court
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

const galleryImages = [
  'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=1200&q=80',
  'https://images.unsplash.com/photo-1521537634581-0dced2fee2ef?w=1200&q=80',
  'https://images.unsplash.com/photo-1613918108466-292b78a8ef95?w=1200&q=80',
]
</script>

<template>
  <div v-if="show" class="modal-backdrop gallery-modal" @click.self="emit('close')">
    <div class="modal-card modal-lg">
      <div class="modal-header">
        <h3 class="modal-title">📷 Thư Viện Hình Ảnh: {{ court.name }}</h3>
        <button class="modal-close-btn" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="gallery-grid">
          <img
            v-for="(img, idx) in galleryImages"
            :key="idx"
            :src="img"
            :alt="`${court.name} - Ảnh ${idx + 1}`"
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
