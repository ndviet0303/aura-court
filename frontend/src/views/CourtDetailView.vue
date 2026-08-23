<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useBookingStore } from '@/stores/booking'
import AuraLoading from '@/components/AuraLoading.vue'

const { t } = useI18n()
const route = useRoute()
const store = useBookingStore()
const court = computed(() => store.courts.find((item) => item.id === Number(route.params.id)) || store.courts[0])

onMounted(async () => {
  try { await store.loadCourts() } catch { /* store exposes the error */ }
})

function openMaps(): void {
  if (court.value?.venue.google_maps_url) window.open(court.value.venue.google_maps_url, '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <section class="page-shell">
    <p v-if="store.error" class="error">{{ store.error }}</p>
    <div v-if="store.loading && !store.courts.length" style="padding: 60px 0;">
      <AuraLoading size="lg" :text="t('common.loading')" />
    </div>
    <template v-else-if="court">
      <div class="eyebrow">{{ court.venue.name }} / {{ court.name }}</div>
      <div class="detail-hero">
        <div>
          <h1 class="page-title">{{ court.name }}</h1>
          <p class="muted">{{ court.description || t('courtDetail.description') }}</p>
          <div class="detail-stats">
            <span>
              <strong>{{ court.reviews_avg_rating ? Number(court.reviews_avg_rating).toFixed(1) : '—' }}</strong>
              {{ court.reviews_count || 0 }} {{ t('booking.rateTitle') }}
            </span>
            <span><strong>60m</strong> {{ t('home.search.timeSlot') }}</span>
            <span><strong>06:00–23:00</strong> {{ t('common.status.open') }}</span>
          </div>
          <div class="actions">
            <RouterLink :to="`/courts/${court.id}/schedule`" class="btn">
              {{ t('courtDetail.viewSchedule') }} <span class="material-symbols-outlined">arrow_forward</span>
            </RouterLink>
            <button v-if="court.venue.google_maps_url" type="button" class="btn secondary" @click="openMaps">
              {{ t('directions.openGoogleMaps') }}
            </button>
          </div>
        </div>
        <img class="detail-image" :src="court.image_url || '/aura/court-4.jpg'" :alt="court.name">
      </div>
      <div v-if="court.venue.address" class="panel venue-summary">
        <div>
          <div class="eyebrow">{{ t('nav.adminVenues') }}</div>
          <h2>{{ court.venue.name }}</h2>
          <p class="muted">{{ court.venue.address }}</p>
        </div>
        <RouterLink :to="`/venues/${court.venue.slug}/directions`" class="link-button">{{ t('directions.venueInfo') }}</RouterLink>
      </div>
      <div class="feature-row" style="margin-top:28px">
        <article class="feature">
          <span class="feature-icon">light_mode</span>
          <h3>{{ t('courtDetail.proLighting') }}</h3>
          <p class="muted">{{ t('home.features.f2Desc') }}</p>
        </article>
        <article class="feature">
          <span class="feature-icon">air</span>
          <h3>{{ t('courtDetail.climateControl') }}</h3>
          <p class="muted">{{ t('courtDetail.standardFloor') }}</p>
        </article>
        <article class="feature">
          <span class="feature-icon">local_parking</span>
          <h3>{{ t('courtDetail.amenities') }}</h3>
          <p class="muted">{{ t('courtDetail.amenitiesList') }}</p>
        </article>
      </div>
    </template>
  </section>
</template>
