<script setup lang="ts">
import SkeletonLoader from '@/shared/components/feedback/SkeletonLoader.vue'
</script>

<template>
  <div class="org-skeleton-view">
    <!-- Header skeleton -->
    <div class="skeleton-hero-card">
      <div class="hero-top-row">
        <SkeletonLoader type="avatar" width="64px" height="64px" rounded="18px" />
        <div class="hero-text-col">
          <SkeletonLoader type="title" width="280px" height="24px" />
          <SkeletonLoader type="text" width="400px" height="14px" />
        </div>
      </div>
      <div class="hero-tabs-row">
        <SkeletonLoader type="rect" width="140px" height="38px" rounded="10px" />
        <SkeletonLoader type="rect" width="160px" height="38px" rounded="10px" />
        <SkeletonLoader type="rect" width="150px" height="38px" rounded="10px" />
        <SkeletonLoader type="rect" width="140px" height="38px" rounded="10px" />
      </div>
    </div>

    <!-- KPI bar skeleton -->
    <div class="skeleton-kpi-grid">
      <SkeletonLoader v-for="i in 5" :key="i" type="rect" height="76px" rounded="16px" />
    </div>

    <!-- Content tab skeleton -->
    <div class="skeleton-content-card">
      <SkeletonLoader type="title" width="220px" height="22px" />
      <SkeletonLoader type="text" width="360px" height="14px" />
      <div class="skeleton-form-grid">
        <SkeletonLoader v-for="i in 6" :key="i" type="rect" height="44px" rounded="12px" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.org-skeleton-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}

.skeleton-hero-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.hero-top-row {
  display: flex;
  align-items: center;
  gap: 20px;
}

.hero-text-col {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
}

.hero-tabs-row {
  display: flex;
  gap: 10px;
  border-top: 1px solid var(--outline);
  padding-top: 18px;
}

.skeleton-kpi-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 14px;
}

@media (max-width: 1024px) {
  .skeleton-kpi-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 640px) {
  .skeleton-kpi-grid {
    grid-template-columns: 1fr;
  }
}

.skeleton-content-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 28px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.skeleton-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-top: 12px;
}

@media (max-width: 768px) {
  .skeleton-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
