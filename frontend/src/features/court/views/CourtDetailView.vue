<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { AuraButton, AuraLoading } from '@/shared/components'
import { useStartChat } from '@/features/chat/composables/useStartChat'
import { useCourtDetail } from '../composables/useCourtDetail'

import CourtDetailHero from '../components/detail/CourtDetailHero.vue'
import CourtSpecsGrid from '../components/detail/CourtSpecsGrid.vue'
import CourtAmenitiesGrid from '../components/detail/CourtAmenitiesGrid.vue'
import CourtReviewsSection from '../components/detail/CourtReviewsSection.vue'
import CourtStickyBookingCard from '../components/detail/CourtStickyBookingCard.vue'
import CourtGalleryModal from '../components/detail/CourtGalleryModal.vue'

const { court, loading, error, isGalleryOpen, basePrice, loadCourt } = useCourtDetail()
const { t } = useI18n()
const auth = useAuthStore()
const startChat = useStartChat()
const chatError = ref('')

// UC-7.14: the customer opens the chat with this court's venue.
async function messageVenue() {
  const venueId = court.value?.venue_id
  if (!venueId) return
  chatError.value = (await startChat({ type: 'customer_venue', venue_id: venueId })) ?? ''
}
</script>

<template>
  <div class="court-detail-view-page">
    <div v-if="court" class="detail-layout-grid">
      <!-- Left Column (Main Information) -->
      <div class="main-info-col">
        <!-- Hero and Gallery -->
        <CourtDetailHero :court="court" @open-gallery="isGalleryOpen = true" />

        <!-- Technical Specs -->
        <CourtSpecsGrid
          :sport-type="court.sport_type"
          :surface-type="court.surface_type"
          :capacity="court.capacity"
        />

        <!-- Amenities -->
        <CourtAmenitiesGrid :amenities="court.amenities" />

        <!-- Player Reviews & Ratings Section -->
        <CourtReviewsSection :court="court" />
      </div>

      <!-- Right Column (Sticky Booking Widget) -->
      <div class="sidebar-sticky-col">
        <CourtStickyBookingCard :court="court" :base-price="basePrice" />
        <AuraButton v-if="auth.user" class="message-venue" variant="outline" @click="messageVenue">
          {{ t('chat.messageVenue') }}
        </AuraButton>
        <p v-if="chatError" class="form-error" role="alert">{{ chatError }}</p>
      </div>
    </div>

    <div v-else-if="error" class="detail-load-error" role="alert">
      <p class="form-error">{{ error }}</p>
      <AuraButton variant="outline" @click="loadCourt">{{ t('errorModal.retry') }}</AuraButton>
    </div>
    <AuraLoading v-else-if="loading" />

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

.detail-load-error {
  display: grid;
  justify-items: center;
  gap: 12px;
  padding: 64px 0;
  text-align: center;
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

.message-venue {
  width: 100%;
  margin-top: 12px;
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
