<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import type { AvailabilitySlot, Court } from '@/features/court/types/court'
import { formatCurrency } from '@/i18n'
import { DatePicker, SelectDropdown, SkeletonLoader } from '@/shared/components'
import { resolveCourtImage } from '@/lib/courtImage'
import { getVenueLocalDate, formatSlotRange } from '../utils/homeAvailability'
import { describeCourtPlace } from '../utils/courtArea'
import { useCourtAreaFilter } from '../composables/useCourtAreaFilter'
import CourtAreaTabs from '../components/CourtAreaTabs.vue'
import LoyaltyPointSelector from '@/features/booking/components/LoyaltyPointSelector.vue'
import CouponCodeField from '@/features/booking/components/CouponCodeField.vue'
import { useCheckoutCoupon } from '@/features/booking/composables/useCheckoutCoupon'
import type { LoyaltySelection } from '@/features/booking/utils/reservation'
const { t, locale } = useI18n()
const store = useBookingStore()
const coupon = reactive(useCheckoutCoupon())
const auth = useAuthStore()
const router = useRouter()
const selected = ref<number | null>(null)
const selectedCourtObj = computed(
  () => store.courts.find((c: Court) => c.id === selected.value) || null,
)
const selectedTimezone = computed(
  () =>
    selectedCourtObj.value?.timezone ||
    selectedCourtObj.value?.venue?.timezone ||
    'Asia/Ho_Chi_Minh',
)
const date = ref(getVenueLocalDate(new Date(), selectedTimezone.value))
const timeFilter = ref('all')
const sportTypeFilter = ref('all')
const loading = ref(false)
const error = ref('')

const sportCategories = [
  { id: 'all', name: 'Tất cả môn', icon: 'apps' },
  { id: 'badminton', name: 'Cầu lông', icon: 'sports_tennis' },
  { id: 'football_5v5', name: 'Bóng đá 5v5', icon: 'sports_soccer' },
  { id: 'football_7v7', name: 'Bóng đá 7v7', icon: 'sports_soccer' },
  { id: 'tennis', name: 'Tennis', icon: 'sports_tennis' },
  { id: 'pickleball', name: 'Pickleball', icon: 'sports_tennis' },
  { id: 'basketball', name: 'Bóng rổ', icon: 'sports_basketball' },
  { id: 'volleyball', name: 'Bóng chuyền', icon: 'sports_volleyball' },
  { id: 'table_tennis', name: 'Bóng bàn', icon: 'table_restaurant' },
]

function getCourtIcon(court: Court): string {
  const sport = (court.sport_zone?.sport_type || '').toLowerCase()
  if (sport === 'football_5v5' || sport === 'football_7v7' || sport === 'football')
    return 'sports_soccer'
  if (sport === 'basketball') return 'sports_basketball'
  if (sport === 'volleyball') return 'sports_volleyball'
  if (sport === 'table_tennis') return 'table_restaurant'
  if (sport === 'tennis' || sport === 'pickleball' || sport === 'badminton') return 'sports_tennis'

  const name = (court.name || '').toLowerCase()
  if (name.includes('bóng đá') || name.includes('football')) return 'sports_soccer'
  if (name.includes('bóng rổ') || name.includes('basketball') || name.includes('nba'))
    return 'sports_basketball'
  if (name.includes('bóng chuyền') || name.includes('volleyball')) return 'sports_volleyball'
  if (name.includes('bóng bàn') || name.includes('table tennis') || name.includes('stiga'))
    return 'table_restaurant'
  return 'sports_tennis'
}

const courtsChipsScrollRef = ref<HTMLElement | null>(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)
const {
  selectedArea,
  query: courtSearchQuery,
  origin,
  locating,
  locateError,
  areas,
  filteredCourts,
  locate,
  reset,
} = useCourtAreaFilter(
  computed(() => store.courts),
  computed(() => t('home.search.otherArea')),
)

function selectArea(key: string | number | null) {
  selectedArea.value = key === null || key === '' || key === 'all' ? null : String(key)
  const targetCourts = filteredCourts.value
  const first = targetCourts[0]
  if (first && !targetCourts.some((c: Court) => c.id === selected.value)) {
    chooseCourt(first.id)
  }
  scrollToSelectedChip()
}
function resetFilters() {
  reset()
  scrollToSelectedChip()
}
async function locateNearest() {
  const nearest = (await locate()) ? filteredCourts.value[0] : undefined
  if (nearest) await chooseCourt(nearest.id)
  else scrollToSelectedChip()
}

function formatCourtChipName(name: string): string {
  return name.replace(/\s*\([^)]*\)/g, '').trim() || name
}

function updateScrollState() {
  if (!courtsChipsScrollRef.value) return
  const el = courtsChipsScrollRef.value
  canScrollLeft.value = el.scrollLeft > 4
  canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 4
}

function scrollCourts(direction: 'left' | 'right') {
  if (!courtsChipsScrollRef.value) return
  const offset = direction === 'left' ? -260 : 260
  if (typeof courtsChipsScrollRef.value.scrollBy === 'function') {
    courtsChipsScrollRef.value.scrollBy({ left: offset, behavior: 'smooth' })
  }
  setTimeout(updateScrollState, 320)
}

function scrollToSelectedChip() {
  nextTick(() => {
    if (!courtsChipsScrollRef.value) return
    const activeChip = courtsChipsScrollRef.value.querySelector(
      '.court-chip.active',
    ) as HTMLElement | null
    if (activeChip && typeof activeChip.scrollIntoView === 'function') {
      activeChip.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' })
    }
    updateScrollState()
  })
}

// Lazy loading for court grid
const gridDisplayLimit = ref(6)
const displayedCourts = computed(() => store.courts.slice(0, gridDisplayLimit.value))
const hasMoreCourts = computed(() => gridDisplayLimit.value < store.courts.length)

function loadMoreCourts() {
  if (gridDisplayLimit.value < store.courts.length) {
    gridDisplayLimit.value = Math.min(gridDisplayLimit.value + 6, store.courts.length)
  }
}

const gridSentinelRef = ref<HTMLElement | null>(null)
let gridObserver: IntersectionObserver | null = null

function setupGridObserver() {
  if (typeof IntersectionObserver === 'undefined') return
  if (gridObserver) gridObserver.disconnect()
  gridObserver = new IntersectionObserver(
    (entries) => {
      if (entries[0]?.isIntersecting) {
        loadMoreCourts()
      }
    },
    { rootMargin: '300px 0px' },
  )
  if (gridSentinelRef.value) {
    gridObserver.observe(gridSentinelRef.value)
  }
}

watch(gridSentinelRef, (el) => {
  if (el) setupGridObserver()
})

onBeforeUnmount(() => {
  if (gridObserver) {
    gridObserver.disconnect()
    gridObserver = null
  }
})

async function selectSportCategory(sportId: string) {
  sportTypeFilter.value = sportId
  gridDisplayLimit.value = 6
  try {
    await store.loadCourts({ sport_type: sportId === 'all' ? undefined : sportId })
    selected.value = store.courts[0]?.id || null
    date.value = getVenueLocalDate(new Date(), selectedTimezone.value)
    await loadAvailability()
    scrollToSelectedChip()
  } catch {
    /* store handles error */
  }
}
const areaOptions = computed(() => [
  { value: 'all', label: `${t('home.search.allAreas')} (${store.courts.length} sân)` },
  ...areas.value.map((a) => ({ value: a.key, label: `${a.label} (${a.count} sân)` })),
])

const selectedAreaValue = computed<string | number>({
  get: () => selectedArea.value ?? 'all',
  set: selectArea,
})

const timeOptions = computed(() => [
  { value: 'all', label: t('home.search.allSlots') },
  { value: 'morning', label: t('home.search.morning') },
  { value: 'evening', label: t('home.search.evening') },
])

const image = (index: number) => resolveCourtImage(store.courts[index])
const visibleSlots = computed(() =>
  store.slots.filter((slot: AvailabilitySlot) => {
    const hour = Number(
      new Date(slot.startsAt).toLocaleTimeString('en-US', {
        timeZone: selectedTimezone.value,
        hour: '2-digit',
        hour12: false,
      }),
    )
    return timeFilter.value === 'morning'
      ? hour < 17
      : timeFilter.value === 'evening'
        ? hour >= 17
        : true
  }),
)
const selectedCourtName = computed(
  () => selectedCourtObj.value?.name || (selected.value ? `Sân ${selected.value}` : ''),
)
const availabilitySummary = computed(() => {
  if (!date.value) return ''
  // date.value is a venue-local YYYY-MM-DD; format it as a UTC midnight so no shift happens.
  const label = new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'vi-VN', {
    weekday: 'long',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(new Date(`${date.value}T00:00:00Z`))
  return t('home.availability.summary', {
    date: label.charAt(0).toUpperCase() + label.slice(1),
    count: visibleSlots.value.filter((slot: AvailabilitySlot) => slot.available).length,
  })
})

let localRequestId = 0

async function loadAvailability() {
  if (selected.value) {
    const reqId = ++localRequestId
    store.selectedSlots = []
    try {
      await store.loadAvailability(selected.value, date.value)
      if (reqId !== localRequestId) return
    } catch {
      /* store exposes the user-facing error */
    }
  }
}

async function chooseCourt(courtId?: string | number) {
  if (courtId !== undefined && courtId !== null) {
    selected.value = Number(courtId)
  }
  date.value = getVenueLocalDate(new Date(), selectedTimezone.value)
  await loadAvailability()
  scrollToSelectedChip()
}

const loyaltyPayload = ref<LoyaltySelection>(null)

async function submit() {
  if (!auth.user) return router.push({ name: 'login', query: { redirect: '/booking' } })
  if (!store.selectedSlots.length) return
  loading.value = true
  error.value = ''
  try {
    const reservation = await store.checkoutReservation(loyaltyPayload.value, coupon.appliedCode)
    router.push({ name: 'reservations', params: { code: reservation.code } })
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}

async function handleSearch() {
  await loadAvailability()
  scrollToAvailability()
}

function scrollToAvailability() {
  document.getElementById('availability')?.scrollIntoView({ behavior: 'smooth' })
}

onMounted(async () => {
  try {
    await store.loadCourts()
    selected.value = store.courts[0]?.id || null
    date.value = getVenueLocalDate(new Date(), selectedTimezone.value)
    await loadAvailability()
    scrollToSelectedChip()
  } catch {
    /* store exposes the user-facing error */
  }
})
</script>

<template>
  <section class="hero">
    <div>
      <div class="eyebrow">{{ t('home.eyebrow') }}</div>
      <h1>
        {{ t('home.heroTitle') }} <em>{{ t('home.heroTitleEmphasis') }}</em>
      </h1>
      <p>{{ t('home.heroSubtitle') }}</p>
      <div class="actions">
        <RouterLink to="/courts" class="btn">
          {{ t('home.bookNow') }} <span class="material-symbols-outlined">arrow_forward</span>
        </RouterLink>
        <a href="#features" class="btn secondary">{{ t('home.exploreAura') }}</a>
      </div>
    </div>
    <div class="hero-mosaic">
      <div>
        <img class="short" src="/aura/hero-football.jpg" alt="Football Pitch" />
        <img class="tall" src="/aura/hero-tennis.jpg" alt="Tennis Court" style="margin-top: 12px" />
      </div>
      <div>
        <img class="tall" src="/aura/hero-pickleball.jpg" alt="Pickleball Court" />
        <div class="access-card" style="margin-top: 12px">
          <div>
            <strong>{{ t('home.open247') }}</strong>
            <span>{{ t('home.openBadge') }}</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Search & Multi-sport Hub Section -->
  <section class="search-hub-section">
    <div class="search-hub-container">
      <!-- Multi-sport Categories Bar -->
      <div class="sport-categories-strip" role="tablist" aria-label="Môn thể thao">
        <button
          v-for="cat in sportCategories"
          :key="cat.id"
          type="button"
          role="tab"
          :aria-selected="sportTypeFilter === cat.id"
          class="sport-category-btn"
          :class="{ active: sportTypeFilter === cat.id }"
          @click="selectSportCategory(cat.id)"
        >
          <span class="material-symbols-outlined">{{ cat.icon }}</span>
          <span>{{ cat.name }}</span>
        </button>
      </div>

      <!-- Main Search Strip -->
      <div class="search-strip">
        <div class="search-field">
          <label>{{ t('home.search.court') }}</label>
          <SelectDropdown
            v-model="selectedAreaValue"
            :options="areaOptions"
            :placeholder="t('home.search.court')"
            :aria-label="t('home.search.court')"
            @change="selectArea"
          />
        </div>
        <div class="search-field">
          <label>{{ t('home.search.date') }}</label>
          <DatePicker v-model="date" @change="loadAvailability" />
        </div>
        <div class="search-field">
          <label>{{ t('home.search.timeSlot') }}</label>
          <SelectDropdown
            v-model="timeFilter"
            :options="timeOptions"
            :placeholder="t('home.search.timeSlot')"
            :aria-label="t('home.search.timeSlot')"
          />
        </div>
        <button
          type="button"
          class="search-action"
          :aria-label="t('home.search.searchAction')"
          @click="handleSearch"
        >
          <span class="material-symbols-outlined">search</span>
        </button>
      </div>
    </div>
  </section>

  <section class="section" id="availability">
    <div class="section-head">
      <div>
        <div class="eyebrow">{{ t('home.availability.eyebrow') }}</div>
        <h2 class="availability-title">
          {{ selectedCourtName || t('home.availability.fallbackTitle') }}
        </h2>
        <p v-if="selectedCourtName && !store.slotsLoading" class="availability-meta">
          {{ availabilitySummary }}
        </p>
      </div>
      <RouterLink to="/courts" class="muted">
        {{ t('home.availability.viewAllCourts') }}
        <span class="material-symbols-outlined">arrow_forward</span>
      </RouterLink>
    </div>
    <p v-if="store.error" class="error">{{ store.error }}</p>
    <!-- Clean Beside-the-slots controls: area tabs, quick search, date & court selector -->
    <div class="availability-beside-bar">
      <!-- Clean Control Header: Area tabs on left, Date & Quick search on right -->
      <div class="availability-bar-header">
        <CourtAreaTabs
          :areas="areas"
          :total="store.courts.length"
          :selected="selectedArea"
          :locating="locating"
          :located="origin !== null"
          :locate-error="locateError"
          @select="selectArea"
          @locate="locateNearest"
        />

        <!-- Quick actions: Search + DatePicker -->
        <div class="availability-quick-actions">
          <div class="availability-search-box">
            <span class="material-symbols-outlined icon-16 search-icon">search</span>
            <input
              v-model="courtSearchQuery"
              type="text"
              class="court-search-input"
              placeholder="Tìm sân, khu vực..."
              aria-label="Tìm sân hoặc khu vực"
            />
            <button
              v-if="courtSearchQuery"
              type="button"
              class="search-clear-btn"
              aria-label="Xóa tìm kiếm"
              @click="courtSearchQuery = ''"
            >
              ×
            </button>
          </div>

          <div class="availability-date-wrapper">
            <DatePicker v-model="date" @change="loadAvailability" />
          </div>
        </div>
      </div>

      <!-- Court Selection Carousel (Compact, elegant, no redundant venue badges) -->
      <div
        class="court-carousel-row"
        role="radiogroup"
        :aria-label="t('home.availability.nearbyCourts')"
      >
        <button
          v-if="filteredCourts.length > 2"
          type="button"
          class="carousel-nav-btn left"
          :class="{ disabled: !canScrollLeft }"
          :disabled="!canScrollLeft"
          aria-label="Cuộn sang trái"
          @click="scrollCourts('left')"
        >
          <span class="material-symbols-outlined icon-14">arrow_back_ios_new</span>
        </button>

        <div
          v-if="filteredCourts.length"
          ref="courtsChipsScrollRef"
          class="court-chips-row"
          @scroll="updateScrollState"
        >
          <button
            v-for="c in filteredCourts"
            :key="c.id"
            type="button"
            role="radio"
            :aria-checked="selected === c.id"
            class="court-chip"
            :class="{ active: selected === c.id }"
            :title="c.name"
            @click="chooseCourt(c.id)"
          >
            <span class="material-symbols-outlined icon-16 court-icon">{{ getCourtIcon(c) }}</span>
            <span class="court-chip-text">
              <span class="court-chip-name">{{ formatCourtChipName(c.name) }}</span>
              <span class="court-chip-place">{{ describeCourtPlace(c, origin) }}</span>
            </span>
          </button>
        </div>

        <div v-else class="no-filtered-courts">
          <span>Không tìm thấy sân phù hợp.</span>
          <button type="button" class="btn-reset-filters" @click="resetFilters">
            Xem tất cả sân
          </button>
        </div>

        <button
          v-if="filteredCourts.length > 2"
          type="button"
          class="carousel-nav-btn right"
          :class="{ disabled: !canScrollRight }"
          :disabled="!canScrollRight"
          aria-label="Cuộn sang phải"
          @click="scrollCourts('right')"
        >
          <span class="material-symbols-outlined icon-14">arrow_forward_ios</span>
        </button>
      </div>
    </div>

    <!-- Time Slots Loading / Loaded States -->
    <div v-if="store.slotsLoading" class="slot-preview">
      <SkeletonLoader type="slot" :count="8" />
    </div>
    <div v-else-if="visibleSlots.length" class="slot-preview">
      <button
        v-for="slot in visibleSlots"
        :key="slot.startsAt"
        class="slot"
        :class="{
          selected: store.selectedSlots.some(
            (item: AvailabilitySlot) => item.startsAt === slot.startsAt,
          ),
          unavailable: !slot.available,
        }"
        :disabled="!slot.available"
        @click="store.toggle(slot)"
      >
        <span class="slot-range font-mono">{{
          formatSlotRange(slot, locale, selectedTimezone)
        }}</span>
        <small class="slot-price">{{ formatCurrency(slot.price) }}</small>
      </button>
    </div>
    <div v-else class="muted" style="margin: 16px 0 28px">
      {{ t('home.availability.noSlots') }}
    </div>

    <!-- Courts Grid Loading / Loaded States -->
    <div v-if="store.courtsLoading && !store.courts.length" class="court-grid">
      <SkeletonLoader type="court-card" :count="3" />
    </div>
    <div v-else class="court-grid-container">
      <div class="court-grid">
        <RouterLink
          v-for="(court, index) in displayedCourts"
          :key="court.id"
          :to="`/courts/${court.slug || court.id}`"
          class="court-card"
        >
          <div class="court-card-image">
            <img :src="image(index)" :alt="court.name" loading="lazy" decoding="async" />
            <span v-if="court.price_slots?.[0]?.price != null" class="tag">
              {{ t('courts.from') }} {{ formatCurrency(court.price_slots[0].price)
              }}{{ t('common.perHour') }}
            </span>
          </div>
          <div class="court-info">
            <h3>{{ court.name }}</h3>
            <p class="muted">
              <span class="material-symbols-outlined">location_on</span>
              {{ court.description || t('courts.defaultLocation') }}
            </p>
          </div>
        </RouterLink>
      </div>

      <!-- Lazy Loading Sentinel & Load More Action -->
      <div v-if="hasMoreCourts" class="lazy-load-action-bar">
        <div ref="gridSentinelRef" class="lazy-sentinel"></div>
        <button type="button" class="btn-load-more-courts" @click="loadMoreCourts">
          <span class="material-symbols-outlined icon-18">expand_more</span>
          <span
            >Xem thêm sân thi đấu ({{ displayedCourts.length }} / {{ store.courts.length }})</span
          >
        </button>
      </div>
    </div>
  </section>

  <section class="section" style="padding-top: 10px" id="features">
    <div class="section-head">
      <div>
        <div class="eyebrow">{{ t('home.features.eyebrow') }}</div>
        <h2>{{ t('home.features.title') }}</h2>
      </div>
    </div>
    <div class="feature-row">
      <article class="feature">
        <span class="feature-icon">bolt</span>
        <h3>{{ t('home.features.f1Title') }}</h3>
        <p class="muted">{{ t('home.features.f1Desc') }}</p>
      </article>
      <article class="feature">
        <span class="feature-icon">verified</span>
        <h3>{{ t('home.features.f2Title') }}</h3>
        <p class="muted">{{ t('home.features.f2Desc') }}</p>
      </article>
      <article class="feature">
        <span class="feature-icon">payments</span>
        <h3>{{ t('home.features.f3Title') }}</h3>
        <p class="muted">{{ t('home.features.f3Desc') }}</p>
      </article>
    </div>
  </section>

  <Transition name="slide-up">
    <div
      v-if="store.selectedSlots.length"
      class="floating-booking"
      role="region"
      :aria-label="
        t('home.floating.summary', {
          count: store.selectedSlots.length,
          total: formatCurrency(coupon.total),
        })
      "
    >
      <!-- Floating Inline Error Notification if booking creation fails -->
      <div v-if="error" class="floating-dock-error" role="alert">
        <span class="material-symbols-outlined icon-18">error</span>
        <span>{{ error }}</span>
        <button
          type="button"
          class="btn-dock-close-err"
          aria-label="Đóng thông báo"
          @click="error = ''"
        >
          ×
        </button>
      </div>

      <!-- Dock Summary Left -->
      <div class="dock-summary">
        <div class="dock-badge">
          <span class="material-symbols-outlined icon-18">schedule</span>
          <span>{{ t('home.floating.slotsCount', { count: store.selectedSlots.length }) }}</span>
        </div>
        <div class="dock-total-box">
          <span class="dock-total-lbl">{{ t('home.floating.totalLabel') }}</span>
          <strong class="dock-total-val">{{ formatCurrency(coupon.total) }}</strong>
        </div>
      </div>

      <!-- Dock Loyalty Point Selector -->
      <div v-if="auth.user && selected" class="dock-loyalty-container">
        <CouponCodeField
          v-model="coupon.code"
          :loading="coupon.loading"
          :error="coupon.error"
          :discount="coupon.discount"
          @apply="coupon.apply"
        />
        <LoyaltyPointSelector
          :court-id="selected"
          :slots="store.selectedSlots.map((s: AvailabilitySlot) => s.startsAt)"
          :coupon-code="coupon.appliedCode ?? undefined"
          @change="loyaltyPayload = $event"
        />
      </div>
      <!-- Dock Actions Right -->
      <div class="dock-actions">
        <button
          type="button"
          class="dock-btn-clear"
          :title="t('home.floating.clear')"
          :aria-label="t('home.floating.clear')"
          @click="store.selectedSlots = []"
        >
          <span class="material-symbols-outlined icon-18">close</span>
        </button>
        <button
          class="btn btn-floating-proceed"
          :disabled="loading || coupon.loading"
          @click="submit"
        >
          <span v-if="loading" class="material-symbols-outlined spin-icon icon-18"
            >progress_activity</span
          >
          <span v-else class="material-symbols-outlined icon-18">payments</span>
          <span>{{ loading ? t('home.floating.processing') : t('home.floating.proceed') }}</span>
          <span v-if="!loading" class="material-symbols-outlined icon-18">arrow_forward</span>
        </button>
      </div>
    </div>
  </Transition>
  <p v-if="error && !store.selectedSlots.length" class="error container">{{ error }}</p>
</template>
<style scoped>
.availability-beside-bar {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 12px 0 20px;
  padding: 12px 16px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.availability-bar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.availability-quick-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.availability-meta {
  margin: 6px 0 0;
  font-size: 14px;
  font-weight: 500;
  color: var(--muted);
}

.availability-search-box {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 34px;
  box-sizing: border-box;
  padding: 0 12px;
  border-radius: 999px;
  background: var(--surface-subtle);
  border: 1px solid var(--outline);
  transition: border-color 0.2s ease;
}

.availability-search-box:focus-within {
  border-color: var(--primary);
}

.search-icon {
  font-size: 15px;
  color: var(--muted);
}

.court-search-input {
  border: none;
  background: transparent;
  font-size: 12px;
  color: var(--ink);
  outline: none;
  width: 110px;
}

.search-clear-btn {
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  font-size: 15px;
  line-height: 1;
  padding: 0;
}

.availability-date-wrapper {
  display: flex;
  align-items: center;
}

/* The shared picker is an underlined form field; here it sits beside pills. */
.availability-date-wrapper :deep(.date-picker) {
  min-width: 0;
}

.availability-date-wrapper :deep(.date-picker-trigger) {
  height: 34px;
  box-sizing: border-box;
  padding: 0 12px;
  gap: 8px;
  border: 1px solid var(--outline);
  border-radius: 999px;
  background: var(--surface-subtle);
  font-size: 12.5px;
  font-weight: 600;
}

.availability-date-wrapper :deep(.date-picker-trigger .material-symbols-outlined) {
  font-size: 16px;
  color: var(--muted);
}

.availability-date-wrapper :deep(.date-picker-trigger:hover) {
  border-color: var(--accent);
}

.no-filtered-courts {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  font-size: 13px;
  color: var(--muted);
  width: 100%;
}

.btn-reset-filters {
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle);
  color: var(--primary);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}
.lazy-load-action-bar {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-top: 24px;
}

.lazy-sentinel {
  width: 100%;
  height: 1px;
  pointer-events: none;
}

.btn-load-more-courts {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 24px;
  border-radius: 999px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-load-more-courts:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: var(--surface-subtle);
  transform: translateY(-2px);
}

:global(html.dark) .btn-load-more-courts {
  background: #1e191c;
  border-color: #382d33;
  color: var(--ink);
}

:global(html.dark) .btn-load-more-courts:hover {
  background: #282126;
  border-color: #e58e9f;
  color: #e58e9f;
}

.court-grid-container {
  display: flex;
  flex-direction: column;
  width: 100%;
}

.court-carousel-row {
  display: flex;
  align-items: stretch;
  gap: 8px;
  width: 100%;
  position: relative;
  padding-top: 12px;
  border-top: 1px solid var(--outline);
}

.carousel-nav-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  margin: 4px 0;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle);
  color: var(--muted);
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.2s ease;
}

.carousel-nav-btn:hover:not(:disabled) {
  border-color: var(--accent);
  color: var(--ink);
  background: var(--soft);
}

.carousel-nav-btn:disabled,
.carousel-nav-btn.disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.court-chips-row {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  flex-wrap: nowrap;
  white-space: nowrap;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  padding: 4px 2px;
  width: 100%;
  scroll-behavior: smooth;
}

.court-chips-row::-webkit-scrollbar {
  display: none;
}

.court-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.court-chip:hover:not(.active) {
  border-color: var(--accent);
  background: var(--soft);
  transform: translateY(-1px);
}

.court-chip.active {
  border-color: var(--primary);
  background: var(--primary);
  color: var(--on-primary);
  box-shadow: 0 2px 10px rgba(107, 45, 63, 0.25);
}

:global(html.dark) .court-chips-row .court-chip {
  background: #1e191c;
  border-color: #382d33;
  color: var(--ink);
}

:global(html.dark) .court-chips-row .court-chip:hover:not(.active) {
  background: #282126;
  border-color: #d8b46e;
}

:global(html.dark) .court-chips-row .court-chip.active {
  background: #e58e9f;
  color: #1c0a10;
  border-color: #e58e9f;
  box-shadow: 0 4px 14px rgba(229, 142, 159, 0.3);
}

:global(html.dark) .carousel-nav-btn {
  background: #231c20;
  border-color: #382d33;
  color: #a89fa4;
}

:global(html.dark) .carousel-nav-btn:hover:not(:disabled) {
  background: #30292e;
  border-color: #d8b46e;
  color: #faf7f3;
}

.court-chip-text {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  line-height: 1.2;
}

.court-chip-name {
  white-space: nowrap;
}

.court-chip-place {
  font-size: 10.5px;
  font-weight: 500;
  opacity: 0.75;
}

@media (max-width: 640px) {
  .availability-beside-bar {
    padding: 10px 12px;
    gap: 10px;
  }
  .availability-bar-header {
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
  }
  .availability-quick-actions {
    justify-content: space-between;
    width: 100%;
  }
}

.slot-range {
  display: block;
  font-size: 12.5px;
  font-weight: 700;
  letter-spacing: -0.2px;
}

.slot-price {
  display: block;
  font-size: 11px;
  font-weight: 600;
  color: var(--muted);
}
</style>
