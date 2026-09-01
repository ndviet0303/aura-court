<script setup lang="ts">
import { onMounted } from 'vue'
import { useCourtsList } from '../composables/useCourtsList'
import { SkeletonLoader } from '@/shared/components'

import CourtsHeroHeader from '../components/list/CourtsHeroHeader.vue'
import CourtsFilterBar from '../components/list/CourtsFilterBar.vue'
import CourtItemCard from '../components/list/CourtItemCard.vue'

const {
  store,
  searchQuery,
  selectedVenue,
  venueOptions,
  filteredCourts,
} = useCourtsList()

onMounted(async () => {
  if (store.courts.length === 0) {
    await store.loadCourts()
  }
})
</script>

<template>
  <div class="courts-view-page">
    <!-- Hero Header -->
    <CourtsHeroHeader
      :total-count="store.courts.length"
      :loading="store.courtsLoading && store.courts.length === 0"
    />

    <!-- Filter & Search Bar -->
    <CourtsFilterBar
      :search="searchQuery"
      :venue="selectedVenue"
      :venue-options="venueOptions"
      @update:search="searchQuery = $event"
      @update:venue="selectedVenue = $event"
    />

    <!-- Skeleton Loading State -->
    <div
      v-if="store.courtsLoading && store.courts.length === 0"
      class="courts-grid"
      data-testid="courts-skeleton"
    >
      <SkeletonLoader type="court-card" :count="6" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="filteredCourts.length === 0"
      class="empty-state-card"
      data-testid="courts-empty-state"
    >
      <span class="material-symbols-outlined empty-icon">search_off</span>
      <h3>{{ $t('courts.emptyTitle') }}</h3>
      <p>{{ $t('courts.emptyDesc') }}</p>
    </div>

    <!-- Courts Grid -->
    <div v-else class="courts-grid" data-testid="courts-grid">
      <CourtItemCard
        v-for="(c, idx) in filteredCourts"
        :key="c.id"
        :court="c"
        :index="idx"
      />
    </div>
  </div>
</template>

<style scoped>
.courts-view-page {
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-width: 1300px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.courts-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
}

.empty-state-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px 20px;
  text-align: center;
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--outline);
}

.empty-icon {
  font-size: 48px;
  margin-bottom: 12px;
  color: var(--muted);
}
</style>
