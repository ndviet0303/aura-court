<script setup lang="ts">
import NavbarSkeleton from '@/shared/layouts/header/NavbarSkeleton.vue'

interface Props {
  type?:
    | 'text'
    | 'title'
    | 'avatar'
    | 'card'
    | 'court-card'
    | 'court-detail'
    | 'slot'
    | 'rect'
    | 'navbar'
  width?: string
  height?: string
  rounded?: string
  count?: number
}

withDefaults(defineProps<Props>(), {
  type: 'text',
  width: '',
  height: '',
  rounded: '',
  count: 1,
})
</script>

<template>
  <div class="skeleton-group">
    <!-- Single / Repeated Elements -->
    <template v-for="i in count" :key="i">
      <!-- Slot Skeleton (Khung giờ trống) -->
      <div v-if="type === 'slot'" class="skeleton-slot" :style="{ '--slot-index': i }">
        <div class="skeleton-item skeleton-slot-time"></div>
        <div class="skeleton-item skeleton-slot-price"></div>
      </div>

      <!-- Full Court Card Skeleton (Thẻ sân) -->
      <div
        v-else-if="type === 'court-card'"
        class="skeleton-court-card"
        :style="{ '--card-index': i }"
      >
        <div class="skeleton-item skeleton-img">
          <div class="skeleton-item skeleton-tag"></div>
        </div>
        <div class="skeleton-court-body">
          <div class="skeleton-item skeleton-title"></div>
          <div class="skeleton-court-meta">
            <div class="skeleton-item skeleton-text"></div>
            <div class="skeleton-item skeleton-text short"></div>
          </div>
          <div class="skeleton-court-footer">
            <div class="skeleton-item skeleton-price"></div>
            <div class="skeleton-court-actions">
              <div class="skeleton-item skeleton-btn-action"></div>
              <div class="skeleton-item skeleton-btn-action primary"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Court Detail Hero Skeleton -->
      <div v-else-if="type === 'court-detail'" class="skeleton-court-detail">
        <div class="skeleton-detail-info">
          <div class="skeleton-item skeleton-eyebrow"></div>
          <div class="skeleton-item skeleton-hero-title"></div>
          <div class="skeleton-item skeleton-text"></div>
          <div class="skeleton-item skeleton-text short"></div>
          <div class="skeleton-detail-stats">
            <div class="skeleton-item skeleton-pill"></div>
            <div class="skeleton-item skeleton-pill"></div>
            <div class="skeleton-item skeleton-pill"></div>
          </div>
          <div class="skeleton-detail-actions">
            <div class="skeleton-item skeleton-btn"></div>
            <div class="skeleton-item skeleton-btn secondary"></div>
          </div>
        </div>
        <div class="skeleton-item skeleton-detail-img"></div>
      </div>

      <!-- General Card Skeleton -->
      <div v-else-if="type === 'card'" class="skeleton-card">
        <div class="skeleton-item skeleton-title"></div>
        <div class="skeleton-item skeleton-text"></div>
        <div class="skeleton-item skeleton-text short"></div>
      </div>

      <!-- Navbar Skeleton -->
      <NavbarSkeleton v-else-if="type === 'navbar'" :fixed="false" />

      <!-- Basic Types -->
      <div
        v-else
        class="skeleton-item"
        :class="[`type-${type}`]"
        :style="{
          width: width || undefined,
          height: height || undefined,
          borderRadius: rounded || undefined,
        }"
      ></div>
    </template>
  </div>
</template>

<style scoped>
.skeleton-group {
  display: contents;
}

/* Base Shimmer Item */
.skeleton-item {
  position: relative;
  overflow: hidden;
  background-color: var(--soft, #eff1f4);
  border-radius: 8px;
}

.skeleton-item::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 209, 220, 0.45) 45%,
    rgba(255, 255, 255, 0.8) 55%,
    rgba(255, 255, 255, 0) 100%
  );
  animation: shimmer-wave 1.6s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

:global(html.dark) .skeleton-item {
  background-color: var(--soft, #2b2e36);
}

:global(html.dark) .skeleton-item::after {
  background: linear-gradient(
    90deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(120, 85, 94, 0.35) 45%,
    rgba(255, 255, 255, 0.1) 55%,
    rgba(255, 255, 255, 0) 100%
  );
}

/* Type variations */
.type-text {
  height: 14px;
  width: 100%;
  border-radius: 6px;
  margin-bottom: 8px;
}

.type-title {
  height: 22px;
  width: 60%;
  border-radius: 8px;
  margin-bottom: 12px;
}

.type-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  flex-shrink: 0;
}

.type-rect {
  width: 100%;
  height: 100%;
  border-radius: 12px;
}

/* Slot Skeleton (Khung giờ trống) */
.skeleton-slot {
  border: 1px solid var(--outline, #d8dce2);
  background: var(--surface-subtle, #f5f6f8);
  border-radius: 10px;
  padding: 12px 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-width: 86px;
  min-height: 58px;
  box-sizing: border-box;
  animation: slot-pulse 1.8s ease-in-out infinite alternate;
  animation-delay: calc((var(--slot-index, 1) - 1) * 0.08s);
}

:global(html.dark) .skeleton-slot {
  background: var(--surface-subtle, #262930);
  border-color: var(--outline, #383c46);
}

.skeleton-slot-time {
  height: 14px;
  width: 46px;
  border-radius: 4px;
}

.skeleton-slot-price {
  height: 11px;
  width: 58px;
  border-radius: 4px;
  opacity: 0.8;
}

/* Court Card Skeleton (Thẻ sân) */
.skeleton-court-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e0e4ea);
  border-radius: 20px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
  animation: card-appear 0.4s ease forwards;
  animation-delay: calc((var(--card-index, 1) - 1) * 0.07s);
}

:global(html.dark) .skeleton-court-card {
  background: var(--surface, #1e1a1c);
  border-color: var(--outline, #3e3539);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.skeleton-img {
  height: 200px;
  width: 100%;
  border-radius: 0;
  position: relative;
}

.skeleton-tag {
  position: absolute;
  top: 12px;
  left: 12px;
  height: 24px;
  width: 96px;
  border-radius: 6px;
  border: 1px solid var(--outline, #d8dce2);
  background: rgba(0, 0, 0, 0.2);
  backdrop-filter: blur(4px);
  z-index: 2;
}

:global(html.dark) .skeleton-tag {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.15);
}

.skeleton-court-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  flex: 1;
}

.skeleton-title {
  height: 20px;
  width: 65%;
  border-radius: 6px;
}

.skeleton-court-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.skeleton-text {
  height: 14px;
  width: 50%;
  border-radius: 4px;
}

.skeleton-text.short {
  width: 75%;
  height: 12px;
}

.skeleton-court-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid var(--outline, #e0e4ea);
}

.skeleton-price {
  height: 20px;
  width: 80px;
  border-radius: 4px;
}

.skeleton-court-actions {
  display: flex;
  gap: 8px;
}

.skeleton-btn-action {
  height: 32px;
  width: 68px;
  border-radius: 8px;
}

.skeleton-btn-action.primary {
  width: 76px;
}

/* Court Detail Hero Skeleton */
.skeleton-court-detail {
  display: grid;
  grid-template-columns: 1.15fr 0.85fr;
  gap: 40px;
  align-items: center;
  margin-bottom: 28px;
}

@media (max-width: 800px) {
  .skeleton-court-detail {
    grid-template-columns: 1fr;
  }
}

.skeleton-detail-info {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.skeleton-eyebrow {
  height: 14px;
  width: 140px;
  border-radius: 4px;
}

.skeleton-hero-title {
  height: 38px;
  width: 70%;
  border-radius: 8px;
}

.skeleton-detail-stats {
  display: flex;
  gap: 14px;
  margin-top: 10px;
}

.skeleton-pill {
  height: 32px;
  width: 100px;
  border-radius: 999px;
}

.skeleton-detail-actions {
  display: flex;
  gap: 12px;
  margin-top: 14px;
}

.skeleton-btn {
  height: 42px;
  width: 130px;
  border-radius: 999px;
}

.skeleton-btn.secondary {
  width: 110px;
}

.skeleton-detail-img {
  height: 320px;
  width: 100%;
  border-radius: 24px;
}

/* Card Skeleton */
.skeleton-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 18px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 16px;
}

.skeleton-card:last-child {
  margin-bottom: 0;
}

@keyframes shimmer-wave {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

@keyframes slot-pulse {
  0% {
    opacity: 0.7;
    transform: scale(0.99);
  }
  100% {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes card-appear {
  from {
    opacity: 0.6;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .skeleton-item::after {
    animation: none;
  }
  .skeleton-slot,
  .skeleton-court-card {
    animation: none;
  }
}
</style>
