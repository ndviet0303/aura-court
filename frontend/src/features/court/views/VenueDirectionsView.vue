<script setup lang="ts">
import { useVenueDirections } from '../composables/useVenueDirections'
import VenueDirectionsHero from '../components/directions/VenueDirectionsHero.vue'
import VenueMapActionCard from '../components/directions/VenueMapActionCard.vue'
import VenueTransportGuide from '../components/directions/VenueTransportGuide.vue'
import VenueCourtsGrid from '../components/directions/VenueCourtsGrid.vue'

const {
  venue,
  matchedCourt,
  courtsAtVenue,
  loading,
  copied,
  copyAddress,
  openGoogleMaps,
  shareLocation,
} = useVenueDirections()
</script>

<template>
  <div class="venue-directions-page">
    <div v-if="loading" class="directions-loading-state">
      <span class="material-symbols-outlined spinner-icon">progress_activity</span>
      <p>{{ $t('directions.loading') }}</p>
    </div>

    <div v-else-if="venue" class="directions-content-layout">
      <!-- Top Hero Banner -->
      <VenueDirectionsHero :venue="venue" :matched-court="matchedCourt" />

      <!-- Main Map & Actions -->
      <VenueMapActionCard
        :venue="venue"
        :copied="copied"
        @open-maps="openGoogleMaps"
        @copy-address="copyAddress"
        @share="shareLocation"
      />

      <!-- Transport, Parking & Hotline -->
      <VenueTransportGuide :venue="venue" />

      <!-- Courts Available at this Location -->
      <VenueCourtsGrid v-if="courtsAtVenue.length > 0" :courts="courtsAtVenue" />
    </div>
  </div>
</template>

<style scoped>
.venue-directions-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 1100px;
  margin: 0 auto;
  padding: 24px 20px 48px;
  color: var(--ink);
}

.directions-content-layout {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.directions-loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  color: var(--muted);
  gap: 12px;
}

.spinner-icon {
  font-size: 32px;
  color: var(--primary);
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
