<script setup lang="ts">
interface Props {
  type?: 'text' | 'title' | 'avatar' | 'card' | 'court-card' | 'rect'
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
      <!-- Full Court Card Skeleton -->
      <div v-if="type === 'court-card'" class="skeleton-court-card">
        <div class="skeleton-item skeleton-img"></div>
        <div class="skeleton-court-body">
          <div class="skeleton-item skeleton-tag"></div>
          <div class="skeleton-item skeleton-title"></div>
          <div class="skeleton-item skeleton-text"></div>
          <div class="skeleton-court-footer">
            <div class="skeleton-item skeleton-price"></div>
            <div class="skeleton-item skeleton-btn"></div>
          </div>
        </div>
      </div>

      <!-- General Card Skeleton -->
      <div v-else-if="type === 'card'" class="skeleton-card">
        <div class="skeleton-item skeleton-title"></div>
        <div class="skeleton-item skeleton-text"></div>
        <div class="skeleton-item skeleton-text short"></div>
      </div>

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
  background-color: #f1f2f3;
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
  animation: shimmer-wave 1.6s ease-in-out infinite;
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

/* Court Card Skeleton */
.skeleton-court-card {
  background: #ffffff;
  border: 1px solid #e7e8e9;
  border-radius: 24px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  box-shadow: 0 4px 16px rgba(25, 28, 29, 0.03);
}

.skeleton-img {
  height: 220px;
  width: 100%;
  border-radius: 18px;
}

.skeleton-court-body {
  padding: 6px 6px 4px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.skeleton-tag {
  height: 18px;
  width: 70px;
  border-radius: 999px;
}

.skeleton-title {
  height: 20px;
  width: 75%;
}

.skeleton-text {
  height: 14px;
  width: 90%;
}

.skeleton-text.short {
  width: 50%;
}

.skeleton-court-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #f3f4f5;
}

.skeleton-price {
  height: 20px;
  width: 80px;
  border-radius: 6px;
}

.skeleton-btn {
  height: 34px;
  width: 100px;
  border-radius: 999px;
}

/* Card Skeleton */
.skeleton-card {
  background: #ffffff;
  border: 1px solid #e7e8e9;
  border-radius: 18px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

@keyframes shimmer-wave {
  100% {
    transform: translateX(100%);
  }
}
</style>
