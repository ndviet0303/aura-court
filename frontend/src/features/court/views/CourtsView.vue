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
  selectedSportType,
  selectedVenue,
  selectedPriceRange,
  sortBy,
  sportCategories,
  venueOptions,
  hasActiveFilters,
  resetFilters,
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
      :sport-type="selectedSportType"
      :venue="selectedVenue"
      :price-range="selectedPriceRange"
      :sort-by="sortBy"
      :venue-options="venueOptions"
      :total-count="store.courts.length"
      :filtered-count="filteredCourts.length"
      :has-active-filters="hasActiveFilters"
      :sport-categories="sportCategories"
      @update:search="searchQuery = $event"
      @update:sport-type="selectedSportType = $event"
      @update:venue="selectedVenue = $event"
      @update:price-range="selectedPriceRange = $event"
      @update:sort-by="sortBy = $event"
      @reset="resetFilters"
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
      <button
        v-if="hasActiveFilters"
        type="button"
        class="btn-reset-empty"
        data-testid="empty-reset-btn"
        @click="resetFilters"
      >
        <span class="material-symbols-outlined">filter_alt_off</span>
        <span>{{ $t('courts.resetFilters') }}</span>
      </button>
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

.btn-reset-empty {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 16px;
  padding: 10px 20px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--primary);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-reset-empty:hover {
  background: var(--primary-container);
  border-color: var(--primary);
  transform: translateY(-1px);
}
</style>
