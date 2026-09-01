<script setup lang="ts">
import { SkeletonLoader } from '@/shared/components'
</script>

<template>
  <div class="rule-skeleton-card">
    <div class="skeleton-left">
      <SkeletonLoader type="rect" width="36px" height="36px" rounded="8px" />
      <div class="skeleton-text-col">
        <div class="skeleton-title-row">
          <SkeletonLoader type="rect" width="180px" height="18px" rounded="4px" />
          <SkeletonLoader type="rect" width="70px" height="18px" rounded="4px" />
        </div>
        <div class="skeleton-meta-row">
          <SkeletonLoader type="rect" width="120px" height="13px" rounded="4px" />
          <SkeletonLoader type="rect" width="100px" height="13px" rounded="4px" />
          <SkeletonLoader type="rect" width="90px" height="13px" rounded="4px" />
        </div>
      </div>
    </div>
    <div class="skeleton-right">
      <SkeletonLoader type="rect" width="40px" height="24px" rounded="12px" />
      <SkeletonLoader type="rect" width="70px" height="30px" rounded="6px" />
      <SkeletonLoader type="rect" width="30px" height="30px" rounded="6px" />
    </div>
  </div>
</template>

<style scoped>
.rule-skeleton-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  gap: 16px;
}

.skeleton-left {
  display: flex;
  align-items: center;
  gap: 14px;
  flex: 1;
}

.skeleton-text-col {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.skeleton-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.skeleton-meta-row {
  display: flex;
  align-items: center;
  gap: 14px;
}

.skeleton-right {
  display: flex;
  align-items: center;
  gap: 10px;
}
</style>
