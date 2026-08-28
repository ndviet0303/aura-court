<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useBookingStore } from '@/stores/booking'
import { formatCurrency } from '@/i18n'
import type { Court } from '@/types'
import SkeletonLoader from '@/components/SkeletonLoader.vue'

const { t } = useI18n()
const route = useRoute()
const store = useBookingStore()

// State
const selectedDuration = ref<number>(1.0)
const isGalleryOpen = ref(false)
const activePhotoIndex = ref(0)

const court = computed(() => {
  const param = String(route.params.slug || route.params.id || '')
  if (!param) return store.courts[0]
  return store.courts.find((item: Court) => item.slug === param || String(item.id) === param) || store.courts[0]
})

const venueCourtsByZone = computed(() => {
  if (!court.value?.venue_id && !court.value?.venue?.id) return []
  const venueId = court.value.venue_id || court.value.venue?.id
  const siblingCourts = store.courts.filter((c: Court) => (c.venue_id || c.venue?.id) === venueId)

  const groups = new Map<string, { zoneName: string; sportType: string; slotDuration: number; courts: Court[] }>()

  siblingCourts.forEach((c: Court) => {
    const key = c.sport_zone ? String(c.sport_zone.id) : 'default'
    const zoneName = c.sport_zone?.name || 'Khu Sân Cầu Lông'
    const sportType = c.sport_zone?.sport_type || 'badminton'
    const slotDuration = c.sport_zone?.slot_duration || 60

    if (!groups.has(key)) {
      groups.set(key, { zoneName, sportType, slotDuration, courts: [] })
    }
    groups.get(key)!.courts.push(c)
  })

  return Array.from(groups.values())
})

// Gallery photos list
const galleryPhotos = computed(() => {
  const primary = court.value?.image_url || '/aura/court-4.jpg'
  return [
    primary,
    '/aura/court-1.jpg',
    '/aura/court-2.jpg',
    '/aura/court-3.jpg',
    '/aura/court-5.jpg',
    '/aura/court-6.jpg',
    '/aura/court-0.jpg',
  ]
})


// Pricing calculations
const basePrice = computed(() => {
  if (court.value?.price_slots && court.value.price_slots.length > 0) {
    return Math.min(...court.value.price_slots.map((s: { price: number }) => s.price))
  }
  return 120000
})

const peakPrice = computed(() => {
  if (court.value?.price_slots && court.value.price_slots.length > 0) {
    return Math.max(...court.value.price_slots.map((s: { price: number }) => s.price))
  }
  return Math.round(basePrice.value * 1.35)
})

const weekendPrice = computed(() => Math.round(basePrice.value * 1.5))

const estimatedTotal = computed(() => {
  return Math.round(basePrice.value * selectedDuration.value)
})

const rating = computed(() => {
  return court.value?.reviews_avg_rating ? Number(court.value.reviews_avg_rating).toFixed(1) : '4.9'
})

const reviewsCount = computed(() => {
  return court.value?.reviews_count || 128
})

const durationOptions = [
  { value: 1.0, label: '1.0h' },
  { value: 1.5, label: '1.5h' },
  { value: 2.0, label: '2.0h' },
  { value: 2.5, label: '2.5h' },
]

const specs = computed(() => [
  {
    icon: 'grid_on',
    title: t('courtDetail.specMat'),
    desc: t('courtDetail.specMatDesc'),
  },
  {
    icon: 'light_mode',
    title: t('courtDetail.specLighting'),
    desc: t('courtDetail.specLightingDesc'),
  },
  {
    icon: 'height',
    title: t('courtDetail.specCeiling'),
    desc: t('courtDetail.specCeilingDesc'),
  },
  {
    icon: 'air',
    title: t('courtDetail.specVentilation'),
    desc: t('courtDetail.specVentilationDesc'),
  },
])

const amenities = computed(() => [
  { icon: 'wifi', label: t('courtDetail.amenityWifi') },
  { icon: 'local_parking', label: t('courtDetail.amenityParking') },
  { icon: 'shower', label: t('courtDetail.amenityShower') },
  { icon: 'ac_unit', label: t('courtDetail.amenityAirConditioning') },
  { icon: 'lock', label: t('courtDetail.amenityLockers') },
  { icon: 'local_cafe', label: t('courtDetail.amenityBeverages') },
])

onMounted(async () => {
  try {
    await store.loadCourts()
  } catch {
    /* store exposes error */
  }
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})

function openGallery(index = 0) {
  activePhotoIndex.value = index
  isGalleryOpen.value = true
  document.body.style.overflow = 'hidden'
}

function closeGallery() {
  isGalleryOpen.value = false
  document.body.style.overflow = ''
}

function nextPhoto() {
  activePhotoIndex.value = (activePhotoIndex.value + 1) % galleryPhotos.value.length
}

function prevPhoto() {
  activePhotoIndex.value =
    (activePhotoIndex.value - 1 + galleryPhotos.value.length) % galleryPhotos.value.length
}

function handleKeydown(e: KeyboardEvent) {
  if (!isGalleryOpen.value) return
  if (e.key === 'Escape') closeGallery()
  if (e.key === 'ArrowRight') nextPhoto()
  if (e.key === 'ArrowLeft') prevPhoto()
}

function openMaps(): void {
  const v = court.value?.venue
  const mapsUrl = v?.google_maps_url
  if (mapsUrl && (mapsUrl.startsWith('http://') || mapsUrl.startsWith('https://'))) {
    window.open(mapsUrl, '_blank', 'noopener,noreferrer')
    return
  }
  const venueName = v?.name || court.value?.name || 'Aura Court'
  const venueAddress = v?.address || '22 Đường Quốc Hương, Phường Thảo Điền, TP. Thủ Đức, TP. Hồ Chí Minh'
  const query = encodeURIComponent(`${venueName} ${venueAddress}`)
  window.open(`https://www.google.com/maps/search/?api=1&query=${query}`, '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <div class="court-detail-page">
    <div class="container">
      <p v-if="store.error" class="error">{{ store.error }}</p>

      <!-- Skeleton loading state -->
      <div v-if="(store.courtsLoading || store.loading) && !store.courts.length" class="detail-loading">
        <SkeletonLoader type="court-detail" />
      </div>

      <template v-else-if="court">
        <!-- Breadcrumb navigation -->
        <nav class="detail-breadcrumb" aria-label="Breadcrumb">
          <RouterLink to="/" class="crumb-link">{{ t('nav.home') }}</RouterLink>
          <span class="material-symbols-outlined crumb-sep">chevron_right</span>
          <RouterLink to="/courts" class="crumb-link">{{ t('nav.courts') }}</RouterLink>
          <span class="material-symbols-outlined crumb-sep">chevron_right</span>
          <span v-if="court.venue?.name" class="crumb-text">{{ court.venue.name }}</span>
          <span v-if="court.venue?.name" class="material-symbols-outlined crumb-sep">chevron_right</span>
          <span class="crumb-current">{{ court.name }}</span>
        </nav>

        <!-- Interactive Hero Photo Gallery -->
        <section class="gallery-hero">
          <div class="gallery-main" @click="openGallery(0)">
            <img :src="galleryPhotos[0]" :alt="court.name" class="gallery-main-img" />
            <div class="gallery-overlay">
              <button type="button" class="gallery-badge-btn" @click.stop="openGallery(0)">
                <span class="material-symbols-outlined">photo_camera</span>
                <span>{{ t('courtDetail.galleryBadge', { count: galleryPhotos.length }) }}</span>
              </button>
            </div>
          </div>

          <div class="gallery-side">
            <div class="gallery-side-item" @click="openGallery(1)">
              <img :src="galleryPhotos[1]" :alt="`${court.name} - 2`" class="gallery-side-img" />
            </div>
            <div class="gallery-side-item gallery-more-trigger" @click="openGallery(2)">
              <img :src="galleryPhotos[2]" :alt="`${court.name} - 3`" class="gallery-side-img" />
              <div class="gallery-more-overlay">
                <span class="material-symbols-outlined gallery-more-icon">collections</span>
                <span class="gallery-more-text">{{ t('courtDetail.viewMorePhotos', { count: galleryPhotos.length - 2 }) }}</span>
              </div>
            </div>
          </div>
        </section>

        <!-- Main Content Layout (Left Column: Details, Right Column: Sticky Booking) -->
        <div class="detail-layout">
          <!-- Left Column -->
          <div class="detail-main-col">
            <!-- Header section -->
            <div class="detail-header">
              <div class="header-badges">
                <span class="premium-badge">
                  <span class="material-symbols-outlined badge-icon">verified</span>
                  {{ t('courtDetail.premiumVenue') }}
                </span>
                <span v-if="court.sport_zone" class="premium-badge" style="background: var(--surface); border: 1px solid var(--border); color: var(--ink);">
                  <span class="material-symbols-outlined badge-icon text-pink">category</span>
                  {{ court.sport_zone.name }} (Khung {{ court.sport_zone.slot_duration }} phút)
                </span>
                <div class="rating-display">
                  <span class="material-symbols-outlined star-icon">star</span>
                  <strong>{{ rating }}</strong>
                  <span class="rating-count">({{ t('courtDetail.reviewsCount', { count: reviewsCount }) }})</span>
                </div>
              </div>

              <h1 class="court-title">{{ court.venue?.name ? `${court.venue.name} — ` : '' }}{{ court.name }}</h1>

              <div class="address-row" role="button" tabindex="0" @click="openMaps" @keydown.enter="openMaps" title="Nhấn để mở chỉ đường Google Maps">
                <span class="material-symbols-outlined loc-icon">location_on</span>
                <span class="address-text">{{ court.venue?.address || '22 Đường Quốc Hương, Phường Thảo Điền, TP. Thủ Đức, TP. Hồ Chí Minh' }}</span>
                <span class="map-hint">{{ t('courtDetail.openInMaps') }} ↗</span>
              </div>
            </div>

            <div class="section-divider"></div>

            <!-- Other Courts at this Venue Grouped by Zone -->
            <section v-if="venueCourtsByZone.length > 0" class="info-section">
              <h2 class="section-heading">Phân khu & Sân chơi tại cơ sở này</h2>
              <div class="venue-zones-container">
                <div v-for="group in venueCourtsByZone" :key="group.zoneName" class="zone-card-item">
                  <div class="zone-card-header">
                    <div class="zone-title-box">
                      <span class="material-symbols-outlined text-pink icon-18">sports</span>
                      <strong>{{ group.zoneName }}</strong>
                    </div>
                    <span class="zone-duration-badge">{{ group.slotDuration }} phút/lượt</span>
                  </div>
                  <div class="zone-courts-pills">
                    <RouterLink
                      v-for="c in group.courts"
                      :key="c.id"
                      :to="`/courts/${c.slug || c.id}`"
                      class="zone-court-pill"
                      :class="{ 'is-current': c.id === court.id }"
                    >
                      <span class="material-symbols-outlined icon-14">sports_tennis</span>
                      <span>{{ c.name }}</span>
                    </RouterLink>
                  </div>
                </div>
              </div>
            </section>

            <div class="section-divider"></div>

            <!-- About Section -->
            <section class="info-section">
              <h2 class="section-heading">{{ t('courtDetail.aboutTitle') }}</h2>
              <p class="court-desc">
                {{ court.description || t('courtDetail.aboutDefaultDesc') }}
              </p>


              <!-- Court Specs Grid -->
              <div class="specs-grid">
                <div v-for="spec in specs" :key="spec.title" class="spec-card">
                  <span class="material-symbols-outlined spec-icon">{{ spec.icon }}</span>
                  <div class="spec-info">
                    <h4 class="spec-title">{{ spec.title }}</h4>
                    <p class="spec-desc">{{ spec.desc }}</p>
                  </div>
                </div>
              </div>
            </section>

            <div class="section-divider"></div>

            <!-- Amenities Section -->
            <section class="info-section">
              <h2 class="section-heading">{{ t('courtDetail.amenitiesTitle') }}</h2>
              <div class="amenities-grid">
                <div v-for="item in amenities" :key="item.label" class="amenity-card">
                  <span class="material-symbols-outlined amenity-icon">{{ item.icon }}</span>
                  <span class="amenity-label">{{ item.label }}</span>
                </div>
              </div>
            </section>

            <div class="section-divider"></div>

            <!-- Venue Info & Map Card -->
            <section v-if="court.venue" class="info-section">
              <h2 class="section-heading">{{ t('courtDetail.locationTitle') }}</h2>
              <div class="venue-info-card">
                <div class="venue-meta">
                  <div class="venue-eyebrow">{{ t('nav.adminVenues') }}</div>
                  <h3 class="venue-name">{{ court.venue.name }}</h3>
                  <p class="venue-address">{{ court.venue.address || '22 Đường Quốc Hương, Phường Thảo Điền, TP. Thủ Đức, TP. Hồ Chí Minh' }}</p>
                </div>
                <div class="venue-actions">
                  <button type="button" class="btn secondary venue-btn" @click="openMaps">
                    <span class="material-symbols-outlined">map</span>
                    {{ t('courtDetail.openInMaps') }}
                  </button>
                  <RouterLink :to="`/venues/${court.venue.slug}/directions`" class="btn venue-btn">
                    <span class="material-symbols-outlined">navigation</span>
                    {{ t('courtDetail.viewVenueDetails') }}
                  </RouterLink>
                </div>
              </div>

              <!-- Map Preview Card -->
              <div class="map-preview-card" role="button" tabindex="0" @click="openMaps" @keydown.enter="openMaps" title="Nhấn để mở bản đồ Google Maps">
                <div class="map-interactive-layer">
                  <div class="map-pattern-bg"></div>
                  <div class="map-route-line"></div>
                  <div class="map-pin-pulse-wrapper">
                    <div class="map-pin-ring"></div>
                    <div class="map-pin-marker">
                      <span class="material-symbols-outlined map-pin-icon">sports_tennis</span>
                    </div>
                  </div>
                  <div class="map-venue-tag">
                    <div class="map-tag-title">{{ court.venue.name }}</div>
                    <div class="map-tag-address">{{ court.venue.address || '22 Đường Quốc Hương, Thảo Điền, TP. Thủ Đức' }}</div>
                  </div>
                </div>
                <div class="map-bottom-bar">
                  <div class="map-badge">
                    <span class="material-symbols-outlined">location_on</span>
                    <span>Google Maps</span>
                  </div>
                  <span class="map-cta-link">
                    {{ t('courtDetail.openInMaps') }}
                    <span class="material-symbols-outlined map-cta-icon">open_in_new</span>
                  </span>
                </div>
              </div>
            </section>
          </div>

          <!-- Right Column: Sticky Booking Card -->
          <div class="detail-sidebar-col">
            <div class="sticky-booking-card">
              <!-- Rate header -->
              <div class="card-rate-header">
                <div class="rate-box">
                  <span class="rate-label">{{ t('courtDetail.rateLabel') }}</span>
                  <div class="rate-value">
                    <span class="price-num">{{ formatCurrency(basePrice) }}</span>
                    <span class="price-unit">{{ t('courtDetail.perHour') }}</span>
                  </div>
                </div>
                <div class="status-indicator">
                  <span class="status-dot"></span>
                  <span class="status-text">{{ t('courtDetail.availableNow') }}</span>
                </div>
              </div>

              <!-- Pricing breakdown table -->
              <div class="pricing-breakdown-box">
                <h4 class="breakdown-title">{{ t('courtDetail.pricingBreakdown') }}</h4>
                <div class="breakdown-row">
                  <span class="breakdown-label">{{ t('courtDetail.offPeakHours') }}</span>
                  <span class="breakdown-val">{{ formatCurrency(basePrice) }}/h</span>
                </div>
                <div class="breakdown-row">
                  <span class="breakdown-label">{{ t('courtDetail.standardHours') }}</span>
                  <span class="breakdown-val">{{ formatCurrency(peakPrice) }}/h</span>
                </div>
                <div class="breakdown-row">
                  <span class="breakdown-label">{{ t('courtDetail.weekendHours') }}</span>
                  <span class="breakdown-val">{{ formatCurrency(weekendPrice) }}/h</span>
                </div>
                <div class="breakdown-discount-row">
                  <span class="discount-label">
                    <span class="material-symbols-outlined discount-icon">loyalty</span>
                    {{ t('courtDetail.memberDiscount') }}
                  </span>
                  <span class="discount-badge">-15%</span>
                </div>
              </div>

              <!-- Duration selector -->
              <div class="duration-selector-box">
                <label class="duration-title">{{ t('courtDetail.selectDuration') }}</label>
                <div class="duration-buttons">
                  <button
                    v-for="opt in durationOptions"
                    :key="opt.value"
                    type="button"
                    class="duration-btn"
                    :class="{ active: selectedDuration === opt.value }"
                    @click="selectedDuration = opt.value"
                  >
                    {{ opt.label }}
                  </button>
                </div>
              </div>

              <!-- Estimated total preview -->
              <div class="estimate-row">
                <span class="estimate-label">{{ t('courtDetail.estimatedTotal', { duration: selectedDuration }) }}</span>
                <span class="estimate-amount">{{ formatCurrency(estimatedTotal) }}</span>
              </div>

              <!-- CTA Button -->
              <RouterLink
                :to="{ path: `/courts/${court.slug || court.id}/schedule`, query: { duration: selectedDuration } }"
                class="book-cta-btn"
              >
                <span>{{ t('courtDetail.confirmAndBook') }}</span>
                <span class="material-symbols-outlined cta-arrow">arrow_forward</span>
              </RouterLink>

              <!-- Trust badges -->
              <div class="trust-list">
                <div class="trust-item">
                  <span class="material-symbols-outlined trust-icon">verified_user</span>
                  <span>{{ t('courtDetail.cancellationPolicy') }}</span>
                </div>
                <div class="trust-item">
                  <span class="material-symbols-outlined trust-icon">bolt</span>
                  <span>{{ t('courtDetail.instantConfirmation') }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Lightbox Gallery Modal -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="isGalleryOpen" class="gallery-modal" @click.self="closeGallery">
          <div class="modal-header">
            <div class="modal-title">
              <span class="material-symbols-outlined">photo_library</span>
              <span>{{ t('courtDetail.galleryModalTitle') }} ({{ activePhotoIndex + 1 }}/{{ galleryPhotos.length }})</span>
            </div>
            <button type="button" class="modal-close-btn" :aria-label="t('courtDetail.closeGallery')" @click="closeGallery">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div class="modal-body">
            <button
              type="button"
              class="nav-btn prev-btn"
              :aria-label="t('courtDetail.prevPhoto')"
              @click.stop="prevPhoto"
            >
              <span class="material-symbols-outlined">chevron_left</span>
            </button>

            <div class="modal-image-wrapper">
              <img
                :src="galleryPhotos[activePhotoIndex]"
                :alt="`${court?.name} - ${activePhotoIndex + 1}`"
                class="modal-photo"
              />
            </div>

            <button
              type="button"
              class="nav-btn next-btn"
              :aria-label="t('courtDetail.nextPhoto')"
              @click.stop="nextPhoto"
            >
              <span class="material-symbols-outlined">chevron_right</span>
            </button>
          </div>

          <!-- Thumbnails Strip -->
          <div class="modal-thumbnails">
            <button
              v-for="(photo, idx) in galleryPhotos"
              :key="idx"
              type="button"
              class="thumb-btn"
              :class="{ active: activePhotoIndex === idx }"
              @click.stop="activePhotoIndex = idx"
            >
              <img :src="photo" :alt="`Thumbnail ${idx + 1}`" />
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.court-detail-page {
  padding-bottom: 80px;
}

.detail-loading {
  margin-top: 20px;
}

/* Breadcrumbs */
.detail-breadcrumb {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 24px;
  font-size: 13px;
  color: var(--muted);
}

.crumb-link {
  color: var(--muted);
  text-decoration: none;
  transition: color 0.2s ease;
}

.crumb-link:hover {
  color: var(--pink-dark);
}

.crumb-sep {
  font-size: 16px;
  color: var(--outline);
}

.crumb-text {
  color: var(--muted);
}

.crumb-current {
  color: var(--ink);
  font-weight: 600;
}

/* Gallery Hero */
.gallery-hero {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 16px;
  height: 480px;
  border-radius: 20px;
  overflow: hidden;
  margin-bottom: 40px;
}

.gallery-main {
  position: relative;
  height: 100%;
  cursor: pointer;
  overflow: hidden;
  border-radius: 20px;
  background-color: var(--soft);
}

.gallery-main-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.gallery-main:hover .gallery-main-img {
  transform: scale(1.04);
}

.gallery-overlay {
  position: absolute;
  bottom: 16px;
  left: 16px;
  z-index: 2;
}

.gallery-badge-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background-color: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(12px);
  color: #191c1d;
  font-size: 13px;
  font-weight: 600;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 10px;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
  transition: all 0.2s ease;
}

html.dark .gallery-badge-btn {
  background-color: rgba(34, 36, 42, 0.92);
  color: #f3f4f6;
  border-color: rgba(255, 255, 255, 0.1);
}

.gallery-badge-btn:hover {
  background-color: #ffffff;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
}

.gallery-side {
  display: grid;
  grid-template-rows: 1fr 1fr;
  gap: 16px;
  height: 100%;
}

.gallery-side-item {
  position: relative;
  height: 100%;
  overflow: hidden;
  border-radius: 20px;
  cursor: pointer;
  background-color: var(--soft);
}

.gallery-side-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.gallery-side-item:hover .gallery-side-img {
  transform: scale(1.06);
}

.gallery-more-trigger {
  position: relative;
}

.gallery-more-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.65));
  backdrop-filter: blur(2px);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  color: #ffffff;
  transition: background 0.3s ease;
}

.gallery-more-trigger:hover .gallery-more-overlay {
  background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.75));
}

.gallery-more-icon {
  font-size: 28px;
}

.gallery-more-text {
  font-size: 15px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

/* Layout Columns */
.detail-layout {
  display: grid;
  grid-template-columns: 1fr 420px;
  gap: 48px;
  align-items: start;
}

.detail-main-col {
  min-width: 0;
}

/* Header */
.header-badges {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 12px;
}

.premium-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  background-color: var(--pink-light);
  color: var(--pink-dark);
  font-size: 12px;
  font-weight: 700;
  border-radius: 8px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.badge-icon {
  font-size: 15px;
}

.rating-display {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 14px;
  color: var(--ink);
}

.star-icon {
  color: #f59e0b;
  font-size: 18px;
  font-variation-settings: 'FILL' 1;
}

.rating-count {
  color: var(--muted);
}

.court-title {
  font-size: 32px;
  font-weight: 800;
  line-height: 1.25;
  color: var(--ink);
  margin: 0 0 12px 0;
  letter-spacing: -0.02em;
}

.address-row {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--muted);
  font-size: 14px;
  cursor: pointer;
  transition: color 0.2s ease;
}

.address-row:hover {
  color: var(--pink-dark);
}

.loc-icon {
  font-size: 20px;
  color: var(--pink-dark);
  flex-shrink: 0;
}

.map-hint {
  font-size: 12px;
  font-weight: 600;
  text-decoration: underline;
  color: var(--pink-dark);
  margin-left: 4px;
}

.section-divider {
  height: 1px;
  background-color: var(--border);
  margin: 32px 0;
}

/* Info Sections */
.info-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.section-heading {
  font-size: 14px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--ink);
  margin: 0;
}

.court-desc {
  font-size: 16px;
  line-height: 1.7;
  color: var(--muted);
  margin: 0;
}

/* Specs Grid */
.specs-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-top: 8px;
}

.spec-card {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 16px;
  background-color: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 14px;
  transition: transform 0.2s ease, border-color 0.2s ease;
}

.spec-card:hover {
  transform: translateY(-2px);
  border-color: var(--pink);
}

.spec-icon {
  font-size: 24px;
  color: var(--pink-dark);
  padding: 10px;
  background-color: var(--pink-light);
  border-radius: 10px;
  flex-shrink: 0;
}

.spec-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.spec-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
}

.spec-desc {
  font-size: 12px;
  color: var(--muted);
  margin: 0;
}

/* Amenities Grid */
.amenities-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.amenity-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background-color: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 12px;
  transition: all 0.2s ease;
}

.amenity-card:hover {
  background-color: var(--pink-light);
  border-color: var(--pink);
  transform: translateY(-2px);
}

.amenity-icon {
  font-size: 20px;
  color: var(--pink-dark);
}

.amenity-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}

/* Venue Info Card */
.venue-info-card {
  padding: 24px;
  background-color: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 18px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
}

.venue-eyebrow {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--pink-dark);
  margin-bottom: 4px;
}

.venue-name {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink);
  margin: 0 0 6px 0;
}

.venue-address {
  font-size: 14px;
  color: var(--muted);
  margin: 0;
}

.venue-actions {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
}

.venue-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  font-size: 13px;
}

/* Map Preview Card */
.map-preview-card {
  margin-top: 16px;
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid var(--border);
  background-color: var(--card-bg);
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
  transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
}

.map-preview-card:hover {
  transform: translateY(-2px);
  border-color: var(--pink);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.map-interactive-layer {
  height: 160px;
  position: relative;
  background: radial-gradient(circle at 50% 50%, rgba(255, 209, 220, 0.18) 0%, rgba(240, 243, 246, 0.95) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

html.dark .map-interactive-layer {
  background: radial-gradient(circle at 50% 50%, rgba(120, 85, 94, 0.25) 0%, rgba(30, 33, 40, 0.95) 100%);
}

.map-pattern-bg {
  position: absolute;
  inset: 0;
  background-image: 
    linear-gradient(to right, rgba(0, 0, 0, 0.04) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(0, 0, 0, 0.04) 1px, transparent 1px);
  background-size: 24px 24px;
}

html.dark .map-pattern-bg {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
}

.map-pin-pulse-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2;
}

.map-pin-ring {
  position: absolute;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background-color: rgba(255, 209, 220, 0.6);
  animation: map-pulse 2s infinite;
}

@keyframes map-pulse {
  0% {
    transform: scale(0.6);
    opacity: 1;
  }
  100% {
    transform: scale(1.6);
    opacity: 0;
  }
}

.map-pin-marker {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: var(--pink-dark);
  color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(120, 85, 94, 0.4);
  position: relative;
  z-index: 3;
}

.map-pin-icon {
  font-size: 20px;
}

.map-venue-tag {
  position: absolute;
  bottom: 12px;
  left: 16px;
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(8px);
  padding: 6px 12px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  display: flex;
  flex-direction: column;
  z-index: 3;
  max-width: 80%;
}

html.dark .map-venue-tag {
  background: rgba(34, 36, 42, 0.92);
  color: #f3f4f6;
}

.map-tag-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.map-tag-address {
  font-size: 11px;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.map-bottom-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 18px;
  background-color: var(--surface-subtle);
  border-top: 1px solid var(--border);
}

.map-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.map-badge .material-symbols-outlined {
  font-size: 16px;
  color: #ea4335;
}

.map-cta-link {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 700;
  color: var(--pink-dark);
}

.map-cta-icon {
  font-size: 14px;
}

/* Right Sticky Booking Column */
.sticky-booking-card {
  position: sticky;
  top: 96px;
  background-color: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 28px;
  box-shadow: 0 12px 36px rgba(0, 0, 0, 0.06);
  display: flex;
  flex-direction: column;
  gap: 24px;
}

html.dark .sticky-booking-card {
  box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35);
}

.card-rate-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.rate-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.rate-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--muted);
}

.rate-value {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.price-num {
  font-size: 28px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.02em;
}

.price-unit {
  font-size: 14px;
  color: var(--muted);
}

.status-indicator {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  background-color: var(--surface-subtle);
  border: 1px solid var(--border);
  border-radius: 9999px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
  animation: pulse-dot 2s infinite;
}

@keyframes pulse-dot {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  }
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
  }
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
  }
}

.status-text {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
}

/* Pricing Breakdown */
.pricing-breakdown-box {
  background-color: var(--surface-subtle);
  border-radius: 16px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.breakdown-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--ink);
  margin: 0 0 4px 0;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
}

.breakdown-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
}

.breakdown-label {
  color: var(--muted);
}

.breakdown-val {
  font-weight: 600;
  color: var(--ink);
}

.breakdown-discount-row {
  margin-top: 4px;
  padding-top: 8px;
  border-top: 1px dashed var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.discount-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--pink-dark);
}

.discount-icon {
  font-size: 16px;
}

.discount-badge {
  background-color: var(--pink-dark);
  color: #ffffff;
  font-size: 12px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}

/* Duration Selector */
.duration-selector-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.duration-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.duration-buttons {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.duration-btn {
  padding: 10px 0;
  font-size: 13px;
  font-weight: 700;
  border-radius: 10px;
  border: 1px solid var(--border);
  background-color: var(--surface-subtle);
  color: var(--muted);
  cursor: pointer;
  transition: all 0.2s ease;
}

.duration-btn:hover {
  background-color: var(--pink-light);
  color: var(--pink-dark);
  border-color: var(--pink);
}

.duration-btn.active {
  background-color: var(--pink);
  color: #2d141c;
  border-color: var(--pink-dark);
  box-shadow: 0 4px 12px rgba(255, 209, 220, 0.5);
}

/* Estimate row */
.estimate-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background-color: var(--surface-subtle);
  border-radius: 12px;
}

.estimate-label {
  font-size: 13px;
  color: var(--muted);
  font-weight: 500;
}

.estimate-amount {
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

/* Book CTA Button */
.book-cta-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 16px;
  background-color: var(--pink-dark);
  color: #ffffff;
  font-size: 16px;
  font-weight: 700;
  border-radius: 14px;
  text-decoration: none;
  box-shadow: 0 8px 24px rgba(120, 85, 94, 0.25);
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.book-cta-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(120, 85, 94, 0.35);
  background-color: #63434b;
}

.cta-arrow {
  font-size: 20px;
  transition: transform 0.2s ease;
}

.book-cta-btn:hover .cta-arrow {
  transform: translateX(4px);
}

/* Trust list */
.trust-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.trust-item {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
  color: var(--muted);
}

.trust-icon {
  font-size: 18px;
  color: var(--pink-dark);
  flex-shrink: 0;
}

/* Lightbox Modal */
.gallery-modal {
  position: fixed;
  inset: 0;
  z-index: 99999;
  background-color: rgba(10, 10, 14, 0.95);
  backdrop-filter: blur(16px);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 24px 32px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: #ffffff;
}

.modal-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 16px;
  font-weight: 600;
  color: #f3f4f6;
}

.modal-close-btn {
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #ffffff;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.modal-close-btn:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: scale(1.08);
}

.modal-body {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  max-height: calc(100vh - 200px);
}

.nav-btn {
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #ffffff;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.nav-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: scale(1.1);
}

.modal-image-wrapper {
  max-width: 1000px;
  max-height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-photo {
  max-width: 100%;
  max-height: 65vh;
  object-fit: contain;
  border-radius: 16px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
}

.modal-thumbnails {
  display: flex;
  justify-content: center;
  gap: 12px;
  overflow-x: auto;
  padding: 12px 0 0;
}

.thumb-btn {
  width: 72px;
  height: 52px;
  border-radius: 8px;
  overflow: hidden;
  border: 2px solid transparent;
  padding: 0;
  cursor: pointer;
  background: none;
  opacity: 0.5;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.thumb-btn.active,
.thumb-btn:hover {
  opacity: 1;
  border-color: var(--pink);
  transform: translateY(-2px);
}

.thumb-btn img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Modal Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Responsive */
@media (max-width: 1024px) {
  .gallery-hero {
    height: 380px;
  }

  .detail-layout {
    grid-template-columns: 1fr;
    gap: 36px;
  }

  .sticky-booking-card {
    position: static;
  }

  .venue-info-card {
    flex-direction: column;
    align-items: flex-start;
  }

  .venue-actions {
    width: 100%;
  }

  .venue-btn {
    flex: 1;
    justify-content: center;
  }
}

@media (max-width: 768px) {
  .gallery-hero {
    grid-template-columns: 1fr;
    height: 300px;
  }

  .gallery-side {
    display: none;
  }

  .court-title {
    font-size: 24px;
  }

  .specs-grid {
    grid-template-columns: 1fr;
  }

  .amenities-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .venue-actions {
    flex-direction: column;
  }

  .modal-photo {
    max-height: 50vh;
  }
}

.venue-zones-container {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 14px;
}
.zone-card-item {
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
}
.zone-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}
.zone-title-box {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  color: var(--ink, #1a202c);
}
.zone-duration-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 9999px;
  background: var(--pink-light, #fff5f7);
  color: var(--pink-dark, #78555e);
}
.zone-courts-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.zone-court-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f8f9fa);
  color: var(--ink, #1a202c);
  font-size: 12px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s ease;
}
.zone-court-pill:hover {
  border-color: var(--pink, #ffd1dc);
  background: var(--pink-light, #fff5f7);
}
.zone-court-pill.is-current {
  background: var(--pink-dark, #78555e);
  color: #ffffff;
  border-color: var(--pink-dark, #78555e);
  font-weight: 600;
}
</style>

