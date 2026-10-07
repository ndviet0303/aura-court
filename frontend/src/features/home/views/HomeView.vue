<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/core/utils/errorMessage'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import type { AvailabilitySlot, Court } from '@/features/court/types/court'
import { formatCurrency, formatTime } from '@/i18n'
import { DatePicker, SelectDropdown, SkeletonLoader } from '@/shared/components'
import { resolveCourtImage } from '@/lib/courtImage'
import { getVenueLocalDate, formatSlotRange } from '../utils/homeAvailability'
import LoyaltyPointSelector from '@/features/booking/components/LoyaltyPointSelector.vue'
const { t, locale } = useI18n()
const store = useBookingStore()
const auth = useAuthStore()
const router = useRouter()
const selected = ref<number | null>(null)
const selectedCourtObj = computed(() => store.courts.find((c: Court) => c.id === selected.value) || null)
const selectedTimezone = computed(() =>
  selectedCourtObj.value?.timezone ||
  selectedCourtObj.value?.venue?.timezone ||
  'Asia/Ho_Chi_Minh'
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
  if (sport === 'football_5v5' || sport === 'football_7v7' || sport === 'football') return 'sports_soccer'
  if (sport === 'basketball') return 'sports_basketball'
  if (sport === 'volleyball') return 'sports_volleyball'
  if (sport === 'table_tennis') return 'table_restaurant'
  if (sport === 'tennis' || sport === 'pickleball' || sport === 'badminton') return 'sports_tennis'

  const name = (court.name || '').toLowerCase()
  if (name.includes('bóng đá') || name.includes('football')) return 'sports_soccer'
  if (name.includes('bóng rổ') || name.includes('basketball') || name.includes('nba')) return 'sports_basketball'
  if (name.includes('bóng chuyền') || name.includes('volleyball')) return 'sports_volleyball'
  if (name.includes('bóng bàn') || name.includes('table tennis') || name.includes('stiga')) return 'table_restaurant'
  return 'sports_tennis'
}

const courtsChipsScrollRef = ref<HTMLElement | null>(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)

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
    const activeChip = courtsChipsScrollRef.value.querySelector('.court-chip.active') as HTMLElement | null
    if (activeChip && typeof activeChip.scrollIntoView === 'function') {
      activeChip.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' })
    }
    updateScrollState()
  })
}

async function selectSportCategory(sportId: string) {
  sportTypeFilter.value = sportId
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
const courtOptions = computed(() =>
  store.courts.map((court: Court) => ({
    value: court.id,
    label: court.name
      ? `${court.name}${court.sport_zone?.name ? ` (${court.sport_zone.name})` : ''}`
      : `Sân ${court.id}`,
  }))
)

const timeOptions = computed(() => [
  { value: 'all', label: t('home.search.allSlots') },
  { value: 'morning', label: t('home.search.morning') },
  { value: 'evening', label: t('home.search.evening') },
])

const total = computed(() => store.selectedSlots.reduce((sum: number, slot: AvailabilitySlot) => sum + slot.price, 0))
const image = (index: number) => resolveCourtImage(store.courts[index])
const visibleSlots = computed(() => store.slots.filter((slot: AvailabilitySlot) => {
  const hour = Number(new Date(slot.startsAt).toLocaleTimeString('en-US', {
    timeZone: selectedTimezone.value,
    hour: '2-digit',
    hour12: false,
  }))
  return timeFilter.value === 'morning' ? hour < 17 : timeFilter.value === 'evening' ? hour >= 17 : true
}))
const selectedCourtName = computed(() => selectedCourtObj.value?.name || (selected.value ? `Sân ${selected.value}` : ''))
const availabilityHeading = computed(() => {
  if (!selectedCourtName.value) {
    return t('home.availability.fallbackTitle')
  }
  return t('home.availability.title', {
    court: selectedCourtName.value,
    date: date.value,
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

const loyaltyPayload = ref<any>(null)
function onLoyaltyChange(data: any) {
  loyaltyPayload.value = data
}

async function submit() {
  if (!auth.user) return router.push({ name: 'login', query: { redirect: '/booking' } })
  if (!store.selectedSlots.length) return
  loading.value = true
  error.value = ''
  try {
    const result = await store.createBooking(
      undefined,
      undefined,
      loyaltyPayload.value?.loyalty_points,
      loyaltyPayload.value?.loyalty_policy_version,
      loyaltyPayload.value?.idempotency_key,
    )
    router.push('/payment/' + result.booking.code)
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
      <h1>{{ t('home.heroTitle') }} <em>{{ t('home.heroTitleEmphasis') }}</em></h1>
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
        <img class="short" src="/aura/court-1.jpg" alt="Aura Court" />
        <img class="tall" src="/aura/court-2.jpg" alt="Aura Court Floor" style="margin-top:12px" />
      </div>
      <div>
        <img class="tall" src="/aura/court-3.jpg" alt="Badminton Player" />
        <div class="access-card" style="margin-top:12px">
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
            v-model="selected"
            :options="courtOptions"
            :placeholder="t('home.search.court')"
            :aria-label="t('home.search.court')"
            @change="chooseCourt"
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
        <h2 class="availability-title">{{ availabilityHeading }}</h2>
      </div>
      <RouterLink to="/courts" class="muted">
        {{ t('home.availability.viewAllCourts') }} <span class="material-symbols-outlined">arrow_forward</span>
      </RouterLink>
    </div>
    <p v-if="store.error" class="error">{{ store.error }}</p>
    <!-- Beside-the-slots controls: selected court, play date & nearby court selector -->
    <div class="availability-beside-bar">
      <div class="availability-bar-top">
        <div class="availability-bar-heading">
          <span class="material-symbols-outlined icon-18">stadium</span>
          <span class="picker-label">{{ t('home.availability.nearbyCourts') }}:</span>
          <span v-if="store.courts.length" class="courts-count-pill">{{ store.courts.length }} sân</span>
        </div>
        <div class="availability-date-picker">
          <span class="picker-label">{{ t('home.availability.playDateLabel') }}:</span>
          <DatePicker v-model="date" @change="loadAvailability" />
        </div>
      </div>

      <div class="court-carousel-row" role="radiogroup" :aria-label="t('home.availability.nearbyCourts')">
        <button
          v-if="store.courts.length > 2"
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
          ref="courtsChipsScrollRef"
          class="court-chips-row"
          @scroll="updateScrollState"
        >
          <button
            v-for="c in store.courts"
            :key="c.id"
            type="button"
            role="radio"
            :aria-checked="selected === c.id"
            class="court-chip"
            :class="{ active: selected === c.id }"
            @click="chooseCourt(c.id)"
          >
            <span class="material-symbols-outlined icon-16">{{ getCourtIcon(c) }}</span>
            <span class="court-chip-name">{{ c.name }}</span>
            <span v-if="c.sport_zone?.name" class="court-chip-zone">{{ c.sport_zone.name }}</span>
          </button>
        </div>

        <button
          v-if="store.courts.length > 2"
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
        :class="{ selected: store.selectedSlots.some((item: AvailabilitySlot) => item.startsAt === slot.startsAt), unavailable: !slot.available }"
        :disabled="!slot.available"
        @click="store.toggle(slot)"
      >
        <span class="slot-range font-mono">{{ formatSlotRange(slot, locale, selectedTimezone) }}</span>
        <small class="slot-price">{{ formatCurrency(slot.price) }}</small>
      </button>
    </div>
    <div v-else class="muted" style="margin: 16px 0 28px;">
      {{ t('home.availability.noSlots') }}
    </div>

    <!-- Courts Grid Loading / Loaded States -->
    <div v-if="store.courtsLoading && !store.courts.length" class="court-grid">
      <SkeletonLoader type="court-card" :count="3" />
    </div>
    <div v-else class="court-grid">
      <RouterLink
        v-for="(court, index) in store.courts"
        :key="court.id"
        :to="`/courts/${court.slug || court.id}`"
        class="court-card"
      >
        <div class="court-card-image">
          <img :src="image(index)" :alt="court.name" />
          <span class="tag">
            {{ t('courts.from') }} {{ formatCurrency(court.price_slots?.[0]?.price || 120000) }}{{ t('common.perHour') }}
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
  </section>

  <section class="section" style="padding-top:10px" id="features">
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
      :aria-label="t('home.floating.summary', { count: store.selectedSlots.length, total: formatCurrency(total) })"
    >
      <!-- Floating Inline Error Notification if booking creation fails -->
      <div v-if="error" class="floating-dock-error" role="alert">
        <span class="material-symbols-outlined icon-18">error</span>
        <span>{{ error }}</span>
        <button type="button" class="btn-dock-close-err" aria-label="Đóng thông báo" @click="error = ''">×</button>
      </div>

      <!-- Dock Summary Left -->
      <div class="dock-summary">
        <div class="dock-badge">
          <span class="material-symbols-outlined icon-18">schedule</span>
          <span>{{ t('home.floating.slotsCount', { count: store.selectedSlots.length }) }}</span>
        </div>
        <div class="dock-total-box">
          <span class="dock-total-lbl">{{ t('home.floating.totalLabel') }}</span>
          <strong class="dock-total-val">{{ formatCurrency(total) }}</strong>
        </div>
      </div>

      <!-- Dock Loyalty Point Selector -->
      <div v-if="auth.user && selected" class="dock-loyalty-container">
        <LoyaltyPointSelector
          :court-id="selected"
          :slots="store.selectedSlots.map((s: AvailabilitySlot) => s.startsAt)"
          @change="onLoyaltyChange"
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
          :disabled="loading"
          @click="submit"
        >
          <span v-if="loading" class="material-symbols-outlined spin-icon icon-18">progress_activity</span>
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
  margin: 14px 0 20px;
  padding: 14px 18px;
  border-radius: 16px;
  background: var(--surface);
  border: 1px solid var(--outline);
}

.availability-bar-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.availability-bar-heading {
  display: flex;
  align-items: center;
  gap: 8px;
}

.availability-date-picker {
  display: flex;
  align-items: center;
  gap: 10px;
}

.picker-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
  white-space: nowrap;
}

.courts-count-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--surface-subtle);
  color: var(--muted);
  border: 1px solid var(--outline);
}

.court-carousel-row {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  position: relative;
}

.carousel-nav-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 36px;
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

.court-chip-name {
  white-space: nowrap;
}

.court-chip-zone {
  font-size: 11px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 6px;
  background: var(--surface-subtle);
  color: var(--muted);
  border: 1px solid var(--outline);
}

.court-chip.active .court-chip-zone {
  background: rgba(255, 255, 255, 0.2);
  color: inherit;
  border-color: transparent;
}

:global(html.dark) .court-chip.active .court-chip-zone {
  background: rgba(0, 0, 0, 0.2);
  color: inherit;
  border-color: transparent;
}

@media (max-width: 640px) {
  .availability-beside-bar {
    padding: 12px 14px;
    gap: 10px;
  }
  .availability-bar-top {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
  .availability-date-picker {
    width: 100%;
    justify-content: space-between;
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

