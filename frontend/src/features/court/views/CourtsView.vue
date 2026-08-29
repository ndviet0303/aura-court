<script setup lang="ts">
import { onMounted } from 'vue'
import { useCourtsList } from '../composables/useCourtsList'

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
    />

    <!-- Filter & Search Bar -->
    <CourtsFilterBar
      :search="searchQuery"
      :venue="selectedVenue"
      :venue-options="venueOptions"
      @update:search="searchQuery = $event"
      @update:venue="selectedVenue = $event"
    />

    <!-- Courts Grid -->
    <div v-if="filteredCourts.length === 0" class="empty-state-card">
      <span>🏸</span>
      <h3>Không tìm thấy sân đấu phù hợp</h3>
      <p>Thử tìm kiếm với từ khóa khác hoặc bỏ chọn bộ lọc khu vực.</p>
    </div>

    <div v-else class="courts-grid">
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

.empty-state-card span {
  font-size: 48px;
  margin-bottom: 12px;
}
</style>
