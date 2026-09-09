<script setup lang="ts">
import { useCourtDetail } from '../composables/useCourtDetail'

import CourtDetailHero from '../components/detail/CourtDetailHero.vue'
import CourtSpecsGrid from '../components/detail/CourtSpecsGrid.vue'
import CourtAmenitiesGrid from '../components/detail/CourtAmenitiesGrid.vue'
import CourtReviewsSection from '../components/detail/CourtReviewsSection.vue'
import CourtStickyBookingCard from '../components/detail/CourtStickyBookingCard.vue'
import CourtGalleryModal from '../components/detail/CourtGalleryModal.vue'

const {
  court,
  reviews,
  reviewsLoading,
  selectedDuration,
  isGalleryOpen,
  basePrice,
  estimatedTotal,
  durationOptions,
} = useCourtDetail()
</script>

<template>
  <div class="court-detail-view-page">
    <div v-if="court" class="detail-layout-grid">
      <!-- Left Column (Main Information) -->
      <div class="main-info-col">
        <!-- Hero and Gallery -->
        <CourtDetailHero
          :court="court"
          @open-gallery="isGalleryOpen = true"
        />

        <!-- Technical Specs -->
        <CourtSpecsGrid />

        <!-- Amenities -->
        <CourtAmenitiesGrid :amenities="court.amenities" />

        <!-- Player Reviews & Ratings Section -->
        <CourtReviewsSection
          :court="court"
          :reviews="reviews"
          :loading="reviewsLoading"
        />
      </div>

      <!-- Right Column (Sticky Booking Widget) -->
      <div class="sidebar-sticky-col">
        <CourtStickyBookingCard
          :court="court"
          :base-price="basePrice"
          :selected-duration="selectedDuration"
          :estimated-total="estimatedTotal"
          :duration-options="durationOptions"
          @update:selected-duration="selectedDuration = $event"
        />
      </div>
    </div>

    <!-- Photo Gallery Modal -->
    <CourtGalleryModal
      v-if="court"
      :show="isGalleryOpen"
      :court="court"
      @close="isGalleryOpen = false"
    />
  </div>
</template>

<style scoped>
.court-detail-view-page {
  max-width: 1300px;
  margin: 0 auto;
  padding: 24px 20px;
  color: var(--ink);
}

.detail-layout-grid {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 24px;
  align-items: flex-start;
}

.main-info-col {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sidebar-sticky-col {
  position: sticky;
  top: 24px;
}

@media (max-width: 950px) {
  .detail-layout-grid {
    grid-template-columns: 1fr;
  }
  .sidebar-sticky-col {
    position: static;
  }
}
</style>
