<script setup lang="ts">
import { RouterLink } from 'vue-router'
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
          <span class="material-symbols-outlined icon-18">photo_library</span>
          <span>{{ $t('courtDetail.viewMorePhotos', { count: '' }) }}</span>
        </div>
      </div>
    </div>

    <div class="court-header-info">
      <div class="title-rating-row">
        <h1 class="court-title">
          {{ court.name }} · <span class="venue-name">{{ court.venue?.name }}</span>
        </h1>
        <a href="#reviews-section" class="rating-display rating-display-link" :title="$t('courtDetail.reviewsCount', { count: court.reviews_count || 42 })">
          <span class="material-symbols-outlined icon-star">star</span>
          <strong>{{ Number(court.reviews_avg_rating || 4.8).toFixed(1) }}</strong>
          <span class="reviews-count">({{ $t('courtDetail.reviewsCount', { count: court.reviews_count || 42 }) }})</span>
          <span class="material-symbols-outlined icon-12">expand_more</span>
        </a>
      </div>

      <div class="addr-directions-row">
        <div class="addr-left">
          <span class="material-symbols-outlined icon-16">location_on</span>
          <span>{{ court.venue?.address || 'Hà Nội' }}</span>
        </div>
        <RouterLink
          v-if="court.venue"
          :to="`/courts/${court.slug || court.id}/directions`"
          class="btn-directions-pill"
          :title="$t('courtDetail.directions')"
        >
          <span class="material-symbols-outlined icon-14">directions</span>
          <span>{{ $t('courtDetail.directions') }}</span>
          <span class="material-symbols-outlined icon-12">arrow_forward</span>
        </RouterLink>
      </div>

      <p class="court-desc">{{ court.description || $t('courtDetail.aboutDefaultDesc') }}</p>
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
  display: flex;
  align-items: center;
  gap: 6px;
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
  color: var(--primary);
}

.rating-display,
.rating-display-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  text-decoration: none;
  color: var(--ink);
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
  transition: all 0.15s ease;
}

.rating-display-link:hover {
  border-color: var(--accent);
  transform: translateY(-1px);
}

:global(html.dark) .rating-display-link {
  background: #231c20;
  border-color: #382d33;
}

.icon-star {
  font-size: 18px;
  font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  color: var(--accent);
}

.reviews-count {
  font-size: 12px;
  color: var(--muted);
}

.addr-directions-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 12px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

:global(html.dark) .addr-directions-row {
  background: #231c20;
  border-color: #382d33;
}

.addr-left {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--muted);
}

.btn-directions-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 999px;
  background: var(--primary);
  color: var(--on-primary);
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.2);
}

.btn-directions-pill:hover {
  background: var(--primary-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(107, 45, 63, 0.3);
}

:global(html.dark) .btn-directions-pill {
  background: #e58e9f;
  color: #1c0a10;
}

.icon-12 {
  font-size: 12px;
}

.icon-14 {
  font-size: 14px;
}

.icon-16 {
  font-size: 16px;
}

.icon-18 {
  font-size: 18px;
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
