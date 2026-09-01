<script setup lang="ts">
import { SkeletonLoader } from '@/shared/components'
</script>

<template>
  <div class="grid-skeleton-container">
    <div class="skeleton-header">
      <div class="skeleton-title">
        <SkeletonLoader type="rect" width="24px" height="24px" rounded="6px" />
        <SkeletonLoader type="rect" width="200px" height="20px" rounded="4px" />
      </div>
      <div class="skeleton-controls">
        <SkeletonLoader type="rect" width="160px" height="34px" rounded="8px" />
        <SkeletonLoader type="rect" width="120px" height="34px" rounded="8px" />
      </div>
    </div>

    <div class="skeleton-week-grid">
      <div v-for="col in 7" :key="col" class="skeleton-col">
        <SkeletonLoader type="rect" width="100%" height="32px" rounded="8px" />
        <div class="skeleton-slots">
          <SkeletonLoader v-for="s in 5" :key="s" type="rect" width="100%" height="46px" rounded="8px" />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.grid-skeleton-container {
  display: flex;
  flex-direction: column;
  gap: 16px;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
  padding: 20px;
}

.skeleton-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.skeleton-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.skeleton-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.skeleton-week-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 10px;
}

.skeleton-col {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.skeleton-slots {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
</style>
