<script setup lang="ts">
import { SkeletonLoader } from '@/shared/components'

defineProps<{
  rows?: number
}>()
</script>

<template>
  <div class="contracts-skeleton-body">
    <div
      v-for="i in (rows || 5)"
      :key="i"
      class="contract-skeleton-row"
    >
      <div class="cust-col">
        <SkeletonLoader height="16px" width="75%" />
        <SkeletonLoader height="12px" width="50%" class="mt-1" />
      </div>

      <div class="court-col">
        <SkeletonLoader height="14px" width="70%" />
        <SkeletonLoader height="11px" width="45%" class="mt-1" />
      </div>

      <div class="schedule-col">
        <SkeletonLoader height="14px" width="60%" />
        <SkeletonLoader height="11px" width="40%" class="mt-1" />
      </div>

      <div class="price-col">
        <SkeletonLoader height="16px" width="65%" />
        <SkeletonLoader height="11px" width="40%" class="mt-1" />
      </div>

      <div class="status-col">
        <SkeletonLoader height="22px" width="80px" border-radius="6px" />
      </div>

      <div class="actions-col">
        <SkeletonLoader height="30px" width="75px" border-radius="8px" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.contracts-skeleton-body {
  display: flex;
  flex-direction: column;
}

.contract-skeleton-row {
  display: grid;
  grid-template-columns: 2fr 1.5fr 1.5fr 1.2fr 1fr 120px;
  padding: 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
}

.contract-skeleton-row:last-child {
  border-bottom: 0;
}

.cust-col, .court-col, .schedule-col, .price-col {
  display: flex;
  flex-direction: column;
}

.mt-1 {
  margin-top: 4px;
}
</style>
