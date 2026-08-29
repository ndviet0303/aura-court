<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useBookingStore } from '@/stores/booking'
import { formatCurrency } from '@/i18n'
import type { Court } from '../types/court'
import { SkeletonLoader, SelectDropdown } from '@/shared/components'
import { subscribeToVenue } from '@/lib/echo'

const { t } = useI18n()
const store = useBookingStore()

// Filter states
const searchQuery = ref('')
const selectedSportType = ref<string>('all')
const selectedVenue = ref<string>('all')
const maxPrice = ref<number>(500000)
const selectedTimeFilter = ref<string>('all')
const minRating = ref<number>(0)
const sortBy = ref<string>('popular')

const sportOptions = [
  { value: 'all', label: 'Tất cả môn thể thao' },
  { value: 'badminton', label: '🏸 Cầu lông' },
  { value: 'football_5v5', label: '⚽ Bóng đá 5v5' },
  { value: 'football_7v7', label: '⚽ Bóng đá 7v7' },
  { value: 'tennis', label: '🎾 Tennis' },
  { value: 'pickleball', label: '🏓 Pickleball' },
  { value: 'basketball', label: '🏀 Bóng rổ' },
  { value: 'volleyball', label: '🏐 Bóng chuyền' },
  { value: 'table_tennis', label: '🏓 Bóng bàn' },
]

let unsubscribes: Array<() => void> = []

// Image fallback
const image = (court: Court, index: number) =>
  court.image_url || `/aura/court-${(index % 4) + 1}.jpg`

onMounted(async () => {
  try {
    await store.loadCourts()
    setupWebSocketSubscriptions()
  } catch {
    /* store exposes the error */
  }
})

onUnmounted(() => {
  unsubscribes.forEach((unsub) => unsub())
  unsubscribes = []
})

function setupWebSocketSubscriptions() {
  unsubscribes.forEach((unsub) => unsub())
  unsubscribes = []

  const venueIds = new Set<number>()
  store.courts.forEach((court: Court) => {
    if (court.venue_id) venueIds.add(court.venue_id)
    else if (court.venue?.id) venueIds.add(court.venue.id)
  })

  venueIds.forEach((venueId) => {
    unsubscribes.push(
      subscribeToVenue(venueId, (event) => {
        const targetCourt = store.courts.find((c) => c.id === event.court_id)
        if (targetCourt) {
          if (event.status === 'maintenance') {
            targetCourt.is_active = false
          } else if (event.status === 'available') {
            targetCourt.is_active = true
          }
        }
      }),
    )
  })
}

// Venue options
const venueOptions = computed(() => {
  const venues = new Map<string, string>()
  store.courts.forEach((court: Court) => {
    if (court.venue?.name) {
      venues.set(court.venue.name, court.venue.name)
    }
  })
  const list = [{ value: 'all', label: t('courts.allLocations') }]
  venues.forEach((name) => {
    list.push({ value: name, label: name })
  })
  return list
})

// Sort options
const sortOptions = computed(() => [
  { value: 'popular', label: t('courts.sortPopular') },
  { value: 'price_asc', label: t('courts.sortPriceAsc') },
  { value: 'price_desc', label: t('courts.sortPriceDesc') },
  { value: 'rating', label: t('courts.sortRating') },
])

// Get lowest price of a court
function getCourtPrice(court: Court): number {
  if (court.price_slots && court.price_slots.length > 0) {
    return Math.min(...court.price_slots.map((s) => s.price))
  }
  return 120000
}

// Get court rating
function getCourtRating(court: Court): number {
  return court.reviews_avg_rating ? Number(court.reviews_avg_rating) : 5.0
}

// Filtered and sorted courts
const filteredCourts = computed(() => {
  let result = [...store.courts]

  // Search by text (name or venue)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    result = result.filter(
      (court: Court) =>
        court.name.toLowerCase().includes(q) ||
        court.description?.toLowerCase().includes(q) ||
        court.venue?.name.toLowerCase().includes(q) ||
        court.venue?.address?.toLowerCase().includes(q)
    )
  }

  // Filter by Sport Type
  if (selectedSportType.value !== 'all') {
    result = result.filter((court: Court) => court.sport_zone?.sport_type === selectedSportType.value)
  }

  // Filter by Venue
  if (selectedVenue.value !== 'all') {
    result = result.filter((court: Court) => court.venue?.name === selectedVenue.value)
  }

  // Filter by Max Price
  result = result.filter((court: Court) => getCourtPrice(court) <= maxPrice.value)

  // Filter by Min Rating
  if (minRating.value > 0) {
    result = result.filter((court: Court) => getCourtRating(court) >= minRating.value)
  }

  // Sorting
  if (sortBy.value === 'price_asc') {
    result.sort((a, b) => getCourtPrice(a) - getCourtPrice(b))
  } else if (sortBy.value === 'price_desc') {
    result.sort((a, b) => getCourtPrice(b) - getCourtPrice(a))
  } else if (sortBy.value === 'rating') {
    result.sort((a, b) => getCourtRating(b) - getCourtRating(a))
  }

  return result
})


function resetFilters(): void {
  searchQuery.value = ''
  selectedSportType.value = 'all'
  selectedVenue.value = 'all'
  maxPrice.value = 500000
  selectedTimeFilter.value = 'all'
  minRating.value = 0
  sortBy.value = 'popular'
}

function setRating(stars: number): void {
  minRating.value = minRating.value === stars ? 0 : stars
}

// Time filter bento chips
const timeChips = [
  { value: 'all', labelKey: 'courts.allLocations', defaultLabel: 'Tất cả' },
  { value: 'morning', labelKey: 'courts.timeMorning', defaultLabel: 'Sáng' },
  { value: 'afternoon', labelKey: 'courts.timeAfternoon', defaultLabel: 'Chiều' },
  { value: 'evening', labelKey: 'courts.timeEvening', defaultLabel: 'Tối' },
]
</script>

<template>
  <main class="page-shell">
    <div class="courts-page-container">
      <!-- Sidebar Filters -->
      <aside class="courts-sidebar">
        <div class="sidebar-sticky">
          <div class="sidebar-header">
            <span class="sidebar-eyebrow">{{ t('courts.refineSearch') }}</span>
            <div class="eyebrow-accent"></div>
          </div>

          <!-- Sport Type Filter -->
          <div class="filter-group">
            <label class="filter-label">Môn thể thao</label>
            <SelectDropdown
              v-model="selectedSportType"
              :options="sportOptions"
              placeholder="Chọn môn thể thao..."
            />
          </div>

          <!-- Search Input -->
          <div class="filter-group">
            <label class="filter-label">{{ t('common.search') }}</label>
            <div class="search-input-wrap">
              <span class="material-symbols-outlined search-icon">search</span>
              <input
                v-model="searchQuery"
                type="text"
                class="filter-input"
                :placeholder="t('courts.searchPlaceholder')"
              />
              <button
                v-if="searchQuery"
                class="clear-input-btn"
                type="button"
                @click="searchQuery = ''"
              >
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>
          </div>

          <!-- Location / Venue Dropdown -->
          <div class="filter-group">
            <label class="filter-label">{{ t('courts.filterLocation') }}</label>
            <SelectDropdown
              v-model="selectedVenue"
              :options="venueOptions"
              :placeholder="t('courts.filterLocation')"
            />
          </div>


          <!-- Price Range Slider -->
          <div class="filter-group">
            <div class="filter-label-row">
              <label class="filter-label">{{ t('courts.priceRange') }}</label>
              <span class="price-val-badge">{{ formatCurrency(maxPrice) }}</span>
            </div>
            <input
              v-model.number="maxPrice"
              type="range"
              min="80000"
              max="500000"
              step="10000"
              class="price-slider"
            />
            <div class="price-range-extremes">
              <span>80.000 đ</span>
              <span>500.000 đ</span>
            </div>
          </div>

          <!-- Preferred Time (Bento Chips) -->
          <div class="filter-group">
            <label class="filter-label">{{ t('courts.preferredTime') }}</label>
            <div class="time-chips-grid">
              <button
                v-for="chip in timeChips"
                :key="chip.value"
                type="button"
                class="time-chip"
                :class="{ active: selectedTimeFilter === chip.value }"
                @click="selectedTimeFilter = chip.value"
              >
                {{ t(chip.labelKey, chip.defaultLabel) }}
              </button>
            </div>
          </div>

          <!-- Min Rating Filter -->
          <div class="filter-group">
            <div class="filter-label-row">
              <label class="filter-label">{{ t('courts.minRating') }}</label>
              <span v-if="minRating > 0" class="rating-badge">{{ minRating }}+ ★</span>
            </div>
            <div class="star-rating-selector">
              <button
                v-for="star in 5"
                :key="star"
                type="button"
                class="star-btn"
                :class="{ active: star <= minRating }"
                @click="setRating(star)"
              >
                <span class="material-symbols-outlined">star</span>
              </button>
            </div>
          </div>

          <!-- Reset Filter Button -->
          <button type="button" class="btn-reset-filters" @click="resetFilters">
            <span class="material-symbols-outlined">restart_alt</span>
            {{ t('courts.resetFilters') }}
          </button>
        </div>
      </aside>

      <!-- Main Courts Content -->
      <section class="courts-content">
        <!-- Top Bar Header -->
        <div class="courts-header">
          <div class="header-text">
            <div class="eyebrow">{{ t('courts.eyebrow') }}</div>
            <h1 class="page-title">{{ t('courts.title') }}</h1>
            <p class="muted">
              {{ t('courts.countCourts', { count: filteredCourts.length }) }}
            </p>
          </div>

          <!-- Sort Dropdown -->
          <div class="sort-control">
            <span class="sort-label">{{ t('courts.sortBy') }}:</span>
            <div class="sort-dropdown-wrap">
              <SelectDropdown
                v-model="sortBy"
                :options="sortOptions"
                :placeholder="t('courts.sortBy')"
              />
            </div>
          </div>
        </div>

        <p v-if="store.error" class="error">{{ store.error }}</p>

        <!-- Skeleton Loading State -->
        <div
          v-if="(store.courtsLoading || store.loading) && !store.courts.length"
          class="courts-custom-grid"
        >
          <SkeletonLoader type="court-card" :count="6" />
        </div>

        <!-- Empty Filter Results -->
        <div v-else-if="!filteredCourts.length" class="empty-state-card">
          <div class="empty-icon-wrap">
            <span class="material-symbols-outlined empty-icon">sports_tennis</span>
          </div>
          <h3>{{ t('courts.noResults') }}</h3>
          <p class="muted">{{ t('courts.subtitle') }}</p>
          <button type="button" class="btn secondary" @click="resetFilters">
            {{ t('courts.clearFilters') }}
          </button>
        </div>

        <!-- Courts Grid -->
        <div v-else class="courts-custom-grid">
          <article
            v-for="(court, index) in filteredCourts"
            :key="court.id"
            class="aura-court-card"
          >
            <!-- Card Image Box -->
            <div class="aura-card-img-wrap">
              <img
                :src="image(court, index)"
                :alt="court.name"
                class="aura-court-img"
                loading="lazy"
              />

              <!-- Top Left: Availability Badge -->
              <div class="status-badge-pill">
                <span class="status-dot"></span>
                <span>{{ t('courts.openStatus') }}</span>
              </div>

              <!-- Amenities Overlays -->
              <div class="amenities-overlay">
                <span class="amenity-tag">Pro Floor</span>
                <span class="amenity-tag">LED Light</span>
                <span class="amenity-tag">A/C</span>
              </div>
            </div>

            <!-- Card Body -->
            <div class="aura-card-body">
              <div class="court-title-row">
                <div>
                  <h3 class="aura-court-name">
                    <RouterLink :to="`/courts/${court.slug || court.id}`">
                      {{ court.name }}
                    </RouterLink>
                  </h3>
                  <p class="court-address muted">
                    <span class="material-symbols-outlined loc-icon">location_on</span>
                    {{ court.venue?.address || court.description || t('courts.defaultLocation') }}
                  </p>
                </div>
                <div class="court-price-box">
                  <span class="price-value">{{ formatCurrency(getCourtPrice(court)) }}</span>
                  <span class="price-unit">{{ t('common.perHour') }}</span>
                </div>
              </div>

              <div class="aura-card-divider"></div>

              <!-- Card Footer: Stars & Actions -->
              <div class="aura-card-footer">
                <div class="rating-display">
                  <div class="stars-row">
                    <span
                      v-for="s in 5"
                      :key="s"
                      class="material-symbols-outlined star-icon"
                      :class="{ filled: s <= Math.round(getCourtRating(court)) }"
                    >
                      star
                    </span>
                  </div>
                  <span class="review-count">
                    {{ court.reviews_avg_rating ? Number(court.reviews_avg_rating).toFixed(1) : '5.0' }}
                    <span class="count-num">({{ court.reviews_count || 12 }})</span>
                  </span>
                </div>

                <div class="card-action-btns">
                  <RouterLink :to="`/courts/${court.slug || court.id}`" class="btn secondary sm">
                    {{ t('courts.courtDetail') }}
                  </RouterLink>
                  <RouterLink :to="`/courts/${court.slug || court.id}/schedule`" class="btn sm">
                    {{ t('courts.bookNow') }}
                  </RouterLink>
                </div>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </main>
</template>

<style scoped>
.page-shell {
  max-width: 1280px;
  margin: 0 auto;
  padding: 48px 24px 90px;
}

.courts-page-container {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

@media (min-width: 1024px) {
  .courts-page-container {
    flex-direction: row;
    align-items: flex-start;
  }
}

/* Sidebar Styling */
.courts-sidebar {
  width: 100%;
  flex-shrink: 0;
}

@media (min-width: 1024px) {
  .courts-sidebar {
    width: 290px;
  }
}

.sidebar-sticky {
  position: sticky;
  top: 100px;
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 24px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 22px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.sidebar-header {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.sidebar-eyebrow {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--pink-dark, #78555e);
}

.eyebrow-accent {
  width: 36px;
  height: 3px;
  background: var(--pink, #ffd1dc);
  border-radius: 999px;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.filter-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.filter-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  letter-spacing: 0.02em;
}

.price-val-badge,
.rating-badge {
  font-size: 12px;
  font-weight: 700;
  color: var(--pink-dark, #78555e);
}

/* Search input */
.search-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  font-size: 18px;
  color: var(--muted, #5d5f5f);
  pointer-events: none;
}

.filter-input {
  width: 100%;
  padding: 10px 32px 10px 38px;
  border-radius: 12px;
  border: 1px solid var(--outline, #d8dce2);
  background: var(--surface-subtle, #f5f6f8);
  color: var(--ink, #191c1d);
  font-size: 13.5px;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.filter-input:focus {
  border-color: var(--pink-dark, #78555e);
  box-shadow: 0 0 0 3px rgba(255, 209, 220, 0.4);
}

.clear-input-btn {
  position: absolute;
  right: 8px;
  background: transparent;
  border: none;
  color: var(--muted, #5d5f5f);
  cursor: pointer;
  display: grid;
  place-items: center;
  padding: 4px;
}

.clear-input-btn .material-symbols-outlined {
  font-size: 16px;
}

/* Price Slider */
.price-slider {
  -webkit-appearance: none;
  appearance: none;
  width: 100%;
  height: 6px;
  border-radius: 999px;
  background: var(--surface-subtle, #e5e8ec);
  outline: none;
  cursor: pointer;
}

.price-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: var(--pink-dark, #78555e);
  box-shadow: 0 2px 6px rgba(120, 85, 94, 0.3);
  cursor: pointer;
  transition: transform 0.15s;
}

.price-slider::-webkit-slider-thumb:hover {
  transform: scale(1.15);
}

.price-range-extremes {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: var(--muted, #5d5f5f);
}

/* Bento Time Chips */
.time-chips-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.time-chip {
  padding: 8px 10px;
  border-radius: 10px;
  border: 1px solid var(--outline, #d8dce2);
  background: var(--surface-subtle, #f5f6f8);
  color: var(--muted, #5d5f5f);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: center;
}

.time-chip:hover {
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
}

.time-chip.active {
  background: var(--pink, #ffd1dc);
  border-color: var(--pink-dark, #78555e);
  color: var(--pink-dark, #78555e);
  font-weight: 700;
}

/* Star rating selector */
.star-rating-selector {
  display: flex;
  gap: 4px;
}

.star-btn {
  background: transparent;
  border: none;
  padding: 4px;
  cursor: pointer;
  color: var(--outline, #d8dce2);
  transition: color 0.15s, transform 0.15s;
  display: grid;
  place-items: center;
}

.star-btn .material-symbols-outlined {
  font-size: 22px;
}

.star-btn.active,
.star-btn:hover {
  color: #f59e0b;
  transform: scale(1.1);
}

.star-btn.active .material-symbols-outlined {
  font-variation-settings: 'FILL' 1;
}

/* Reset button */
.btn-reset-filters {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 12px;
  border-radius: 14px;
  border: 1px dashed var(--outline, #d8dce2);
  background: transparent;
  color: var(--muted, #5d5f5f);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-reset-filters:hover {
  border-color: var(--pink-dark, #78555e);
  color: var(--pink-dark, #78555e);
  background: var(--surface-subtle, #f5f6f8);
}

/* Content Area */
.courts-content {
  flex: 1;
  min-width: 0;
}

.courts-header {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 28px;
}

@media (min-width: 768px) {
  .courts-header {
    flex-direction: row;
    align-items: flex-end;
  }
}

.header-text h1 {
  margin: 6px 0 6px;
}

.sort-control {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
}

@media (min-width: 768px) {
  .sort-control {
    width: auto;
  }
}

.sort-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  white-space: nowrap;
}

.sort-dropdown-wrap {
  min-width: 190px;
}

/* Courts Grid */
.courts-custom-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

@media (min-width: 700px) {
  .courts-custom-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Court Card */
.aura-court-card {
  background: var(--card-bg, #ffffff);
  border-radius: 24px;
  border: 1px solid var(--border, #e0e4ea);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1),
    box-shadow 0.35s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
}

.aura-court-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 16px 36px rgba(120, 85, 94, 0.12);
  border-color: var(--pink, #ffd1dc);
}

/* Image container */
.aura-card-img-wrap {
  position: relative;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  background: var(--soft, #eff1f4);
}

.aura-court-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.aura-court-card:hover .aura-court-img {
  transform: scale(1.06);
}

/* Availability Badge */
.status-badge-pill {
  position: absolute;
  top: 14px;
  left: 14px;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(8px);
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #15803d;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

:global(html.dark) .status-badge-pill {
  background: rgba(34, 36, 42, 0.92);
  color: #4ade80;
}

.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #22c55e;
  animation: pulse-dot 1.8s infinite;
}

@keyframes pulse-dot {
  0% {
    box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.6);
  }
  70% {
    box-shadow: 0 0 0 6px rgba(34, 197, 94, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
  }
}

/* Amenities Overlay */
.amenities-overlay {
  position: absolute;
  bottom: 12px;
  left: 14px;
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.amenity-tag {
  background: rgba(25, 28, 29, 0.65);
  backdrop-filter: blur(6px);
  color: #ffffff;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

/* Card Body */
.aura-card-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.court-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.aura-court-name {
  margin: 0 0 4px;
  font-size: 19px;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.aura-court-name a {
  color: var(--ink, #191c1d);
  text-decoration: none;
  transition: color 0.2s;
}

.aura-court-name a:hover {
  color: var(--pink-dark, #78555e);
}

.court-address {
  margin: 0;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.loc-icon {
  font-size: 16px;
  color: var(--pink-dark, #78555e);
}

.court-price-box {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  text-align: right;
  flex-shrink: 0;
}

.price-value {
  font-size: 18px;
  font-weight: 800;
  color: var(--pink-dark, #78555e);
  letter-spacing: -0.02em;
}

.price-unit {
  font-size: 11px;
  color: var(--muted, #5d5f5f);
}

.aura-card-divider {
  height: 1px;
  background: var(--border, #e0e4ea);
  margin: 16px 0 14px;
  width: 100%;
}

/* Card Footer */
.aura-card-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: auto;
  gap: 12px;
  flex-wrap: wrap;
}

.rating-display {
  display: flex;
  align-items: center;
  gap: 6px;
}

.stars-row {
  display: flex;
  color: #f59e0b;
}

.star-icon {
  font-size: 17px;
  font-variation-settings: 'FILL' 0;
}

.star-icon.filled {
  font-variation-settings: 'FILL' 1;
}

.review-count {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

.count-num {
  font-weight: 400;
  color: var(--muted, #5d5f5f);
  font-size: 12px;
}

.card-action-btns {
  display: flex;
  gap: 8px;
}

.btn.sm {
  padding: 8px 16px;
  font-size: 12px;
  border-radius: 999px;
}

/* Empty State Card */
.empty-state-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 24px;
  padding: 60px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 14px;
  margin-top: 10px;
}

.empty-icon-wrap {
  width: 68px;
  height: 68px;
  border-radius: 50%;
  background: var(--pink-light, #ffd9e2);
  display: grid;
  place-items: center;
}

.empty-icon {
  font-size: 32px;
  color: var(--pink-dark, #78555e);
}
</style>
