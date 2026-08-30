<script setup lang="ts">
import type { Court } from '../../types/court'

defineProps<{
  court: Court
}>()

const emit = defineEmits<{
  (e: 'openGallery'): void
}>()
</script>

<template>
  <div class="court-hero-card">
    <div class="gallery-main" @click="emit('openGallery')">
      <div class="main-img-wrap">
        <img
          src="https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=1200&q=80"
          alt="Court Banner"
          class="hero-img"
        />
        <div class="gallery-overlay">
          <span>📷 Xem tất cả ảnh sân</span>
        </div>
      </div>
    </div>

    <div class="court-header-info">
      <div class="title-rating-row">
        <h1 class="court-title">
          {{ court.name }} · <span class="venue-name">{{ court.venue?.name }}</span>
        </h1>
        <div class="rating-display">
          ⭐ <strong>{{ court.reviews_avg_rating || 4.8 }}</strong>
          <span class="reviews-count">({{ court.reviews_count || 42 }} đánh giá)</span>
        </div>
      </div>

      <p class="court-addr">📍 {{ court.venue?.address }}</p>
      <p class="court-desc">{{ court.description || 'Sân thi đấu chuyên nghiệp với mặt thảm cao cấp chống trơn trượt.' }}</p>
    </div>
  </div>
</template>

<style scoped>
.court-hero-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
  overflow: hidden;
  padding-bottom: 20px;
}

.gallery-main {
  width: 100%;
  height: 320px;
  position: relative;
  cursor: pointer;
  overflow: hidden;
}

.main-img-wrap {
  width: 100%;
  height: 100%;
  position: relative;
}

.hero-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.gallery-main:hover .hero-img {
  transform: scale(1.03);
}

.gallery-overlay {
  position: absolute;
  bottom: 16px;
  right: 16px;
  padding: 8px 16px;
  border-radius: 20px;
  background: rgba(0, 0, 0, 0.7);
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  backdrop-filter: blur(4px);
}

.court-header-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 0 24px;
}

.title-rating-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.court-title {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
}

.venue-name {
  color: var(--pink-dark, #db2777);
}

.rating-display {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
}

.reviews-count {
  font-size: 12px;
  color: var(--muted);
}

.court-addr {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.court-desc {
  margin: 4px 0 0;
  font-size: 14px;
  line-height: 1.5;
}

@media (max-width: 640px) {
  .gallery-main {
    height: 240px;
  }
  .court-header-info {
    padding: 0 16px;
  }
  .court-title {
    font-size: 20px;
  }
}
</style>
