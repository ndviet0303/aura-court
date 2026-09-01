<script setup lang="ts">
import { SkeletonLoader } from '@/shared/components'

defineProps<{
  totalCount: number
  loading?: boolean
}>()
</script>

<template>
  <div class="courts-hero-header">
    <div class="hero-titles">
      <span class="badge font-mono">
        <span class="material-symbols-outlined badge-icon">verified</span>
        <span>AURA COURT VENUES</span>
      </span>
      <h1 class="page-title">{{ $t('courts.courtsHeroTitle') }}</h1>
      <div v-if="loading && totalCount === 0" class="hero-sub-loading">
        <SkeletonLoader width="360px" height="14px" rounded="4px" />
      </div>
      <p v-else class="page-sub">
        <span class="material-symbols-outlined sub-icon">stadium</span>
        <span>{{ $t('courts.courtsHeroSub', { count: totalCount }) }}</span>
      </p>
    </div>
  </div>
</template>

<style scoped>
.courts-hero-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.hero-titles {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 100%;
}

.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 800;
  color: var(--primary);
  background: var(--primary-container);
  border: 1px solid rgba(107, 45, 63, 0.12);
  padding: 4px 10px;
  border-radius: 8px;
  width: fit-content;
  letter-spacing: 0.5px;
}

:global(html.dark) .badge {
  border-color: rgba(229, 142, 159, 0.25);
}

.badge-icon {
  font-size: 14px;
  color: var(--accent);
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  color: var(--ink);
}

.page-sub {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.sub-icon {
  font-size: 16px;
  color: var(--muted);
}

.hero-sub-loading {
  display: flex;
  align-items: center;
  height: 18px;
  margin: 2px 0;
}
</style>
