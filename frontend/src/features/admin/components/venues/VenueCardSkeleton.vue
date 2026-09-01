<script setup lang="ts">
import { SkeletonLoader } from '@/shared/components'
</script>

<template>
  <div class="venue-admin-card skeleton-card">
    <div class="venue-card-top">
      <div class="venue-meta-info">
        <div class="venue-name-row">
          <SkeletonLoader type="rect" width="160px" height="20px" rounded="6px" />
          <SkeletonLoader type="rect" width="70px" height="18px" rounded="4px" />
        </div>
        <SkeletonLoader type="rect" width="220px" height="14px" rounded="4px" class="mt-1" />
      </div>
      <SkeletonLoader type="rect" width="44px" height="24px" rounded="12px" />
    </div>

    <div class="venue-stats-bar">
      <SkeletonLoader type="rect" width="100px" height="14px" rounded="4px" />
      <SkeletonLoader type="rect" width="80px" height="14px" rounded="4px" />
    </div>

    <div class="venue-card-actions">
      <SkeletonLoader type="rect" width="90px" height="32px" rounded="8px" />
      <SkeletonLoader type="rect" width="80px" height="32px" rounded="8px" />
      <SkeletonLoader type="rect" width="90px" height="32px" rounded="8px" />
    </div>
  </div>
</template>

<style scoped>
.venue-admin-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.venue-card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.venue-meta-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.venue-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.venue-stats-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.venue-card-actions {
  display: flex;
  gap: 8px;
  margin-top: auto;
}

.mt-1 {
  margin-top: 4px;
}
</style>
