<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, errorMessage } from '@/lib/api'
import DatePicker from '@/components/DatePicker.vue'
import SelectDropdown from '@/components/SelectDropdown.vue'
import SkeletonLoader from '@/components/SkeletonLoader.vue'
import { useAuthStore } from '@/stores/auth'
import { useBookingStore } from '@/stores/booking'
import { formatCurrency, formatTime } from '@/i18n'
import type { AvailabilitySlot, Court } from '@/types'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const store = useBookingStore()

// State
const date = ref(new Date().toISOString().slice(0, 10))
const couponCode = ref('')
const selectedCourtId = ref<number>(Number(route.params.id) || 1)
const error = ref('')
const notice = ref('')
const loading = ref(false)

// Active court
const court = computed(() =>
  store.courts.find((item: Court) => item.id === selectedCourtId.value) || store.courts[0]
)

// Unavailable / Closed slots
const unavailableSlots = computed(() => store.slots.filter((slot: AvailabilitySlot) => !slot.available))

// Total price calculation
const totalPrice = computed(() =>
  store.selectedSlots.reduce((sum: number, slot: AvailabilitySlot) => sum + slot.price, 0)
)

// Court Options for Dropdown
const courtOptions = computed(() =>
  store.courts.map((c: Court) => ({
    value: c.id,
    label: `${c.name} (${c.venue?.name || 'Aura Club'})`,
  }))
)

// Generate 14-day date carousel items starting from today
const carouselDays = computed(() => {
  const days = []
  const base = new Date()
  for (let i = 0; i < 14; i++) {
    const d = new Date(base)
    d.setDate(base.getDate() + i)
    const iso = d.toISOString().slice(0, 10)
    const dayNum = d.getDate()
    const dayName = d.toLocaleDateString(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
      weekday: 'short',
    })
    days.push({ iso, dayNum, dayName })
  }
  return days
})

async function fetchAvailability() {
  if (court.value) {
    try {
      await store.loadAvailability(court.value.id, date.value)
    } catch {
      error.value = store.error
    }
  }
}

function selectDate(isoDate: string) {
  date.value = isoDate
  fetchAvailability()
}

function selectCourt(courtId: number) {
  selectedCourtId.value = courtId
  fetchAvailability()
}

onMounted(async () => {
  try {
    await store.loadCourts()
    if (route.params.id) {
      selectedCourtId.value = Number(route.params.id)
    } else if (store.courts.length > 0 && store.courts[0]) {
      selectedCourtId.value = store.courts[0].id
    }
    await fetchAvailability()
  } catch {
    error.value = store.error
  }
})

watch(
  () => route.params.id,
  (newId) => {
    if (newId) {
      selectedCourtId.value = Number(newId)
      fetchAvailability()
    }
  }
)

async function joinWaitlist(slot: { startsAt: string }) {
  if (!auth.user) {
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  try {
    await api.post('/waitlist', { court_id: court.value?.id, starts_at: slot.startsAt })
    notice.value = t('schedule.selectedSlotsCount', { count: 1 })
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  }
}

async function next() {
  if (!store.selectedSlots.length) return
  loading.value = true
  error.value = ''
  try {
    const payload = {
      court_id: court.value?.id,
      slots: store.selectedSlots.map((slot: AvailabilitySlot) => slot.startsAt),
    }
    const result = route.query.reschedule
      ? await api.post<{ booking: { code: string } }>(
          '/bookings/' + route.query.reschedule + '/reschedule',
          payload
        )
      : await store.createBooking(couponCode.value)
    router.push('/payment/' + result.booking.code)
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="page-shell">
    <!-- Header Section with Date Strip -->
    <header class="schedule-header">
      <div class="header-left">
        <div class="eyebrow">{{ t('schedule.eyebrow') }}</div>
        <h1 class="page-title">{{ t('schedule.title') }}</h1>
        <p class="muted">
          {{ court?.name || 'Aura Court' }} · {{ court?.venue?.name || 'Aura Club' }}
        </p>
      </div>

      <!-- Date Carousel Strip -->
      <div class="date-carousel-wrap">
        <div class="date-carousel">
          <button
            v-for="item in carouselDays"
            :key="item.iso"
            type="button"
            class="carousel-date-pill"
            :class="{ active: date === item.iso }"
            @click="selectDate(item.iso)"
          >
            <span class="day-name">{{ item.dayName }}</span>
            <span class="day-num">{{ item.dayNum }}</span>
          </button>
        </div>
        <div class="custom-date-btn-wrap">
          <DatePicker v-model="date" @change="fetchAvailability" />
        </div>
      </div>
    </header>

    <div v-if="route.query.reschedule" class="reschedule-banner">
      <span class="material-symbols-outlined icon-20">sync</span>
      <span>{{ t('schedule.reschedulingNotice', { code: route.query.reschedule }) }}</span>
    </div>

    <!-- Main Content Layout -->
    <div class="schedule-layout">
      <!-- Left / Center: Slots & Grid -->
      <section class="slots-section">
        <!-- Court Switcher & Duration Strip -->
        <div class="court-select-strip">
          <div class="strip-left">
            <label class="court-dropdown-label">{{ t('schedule.selectCourt') }}:</label>
            <div class="court-dropdown-box">
              <SelectDropdown
                v-model="selectedCourtId"
                :options="courtOptions"
                :placeholder="t('schedule.selectCourt')"
                @change="selectCourt(selectedCourtId)"
              />
            </div>
          </div>

          <div class="strip-badge">
            <span class="material-symbols-outlined icon-16">timer</span>
            <span>60m / ca</span>
          </div>
        </div>

        <!-- Legend Status Bar -->
        <div class="schedule-legend-bar">
          <div class="legend-item">
            <span class="legend-indicator available"></span>
            <span>{{ t('schedule.legendAvailable') }}</span>
          </div>
          <div class="legend-item">
            <span class="legend-indicator selected"></span>
            <span>{{ t('schedule.legendSelected') }}</span>
          </div>
          <div class="legend-item">
            <span class="legend-indicator booked"></span>
            <span>{{ t('schedule.legendBooked') }}</span>
          </div>
        </div>

        <!-- Time Slots Grid -->
        <div v-if="store.slotsLoading || (store.loading && !store.slots.length)" class="slots-interactive-grid">
          <SkeletonLoader type="slot" :count="12" />
        </div>

        <div v-else-if="store.slots.length" class="slots-interactive-grid">
          <button
            v-for="slot in store.slots"
            :key="slot.startsAt"
            type="button"
            class="interactive-slot-btn"
            :class="{
              'is-selected': store.selectedSlots.some((item: AvailabilitySlot) => item.startsAt === slot.startsAt),
              'is-unavailable': !slot.available,
            }"
            :disabled="!slot.available"
            @click="store.toggle(slot)"
          >
            <div class="slot-time-row">
              <span class="slot-time">{{ formatTime(slot.startsAt) }}</span>
              <span
                v-if="store.selectedSlots.some((item: AvailabilitySlot) => item.startsAt === slot.startsAt)"
                class="material-symbols-outlined check-icon"
              >
                check_circle
              </span>
              <span v-else-if="!slot.available" class="material-symbols-outlined lock-icon">
                lock
              </span>
            </div>
            <div class="slot-price-row">
              <span class="slot-price">{{ formatCurrency(slot.price) }}</span>
            </div>
          </button>
        </div>

        <!-- No slots in date -->
        <div v-else class="empty-slots-box">
          <span class="material-symbols-outlined empty-icon">event_busy</span>
          <p>{{ t('schedule.noSlots') }}</p>
        </div>

        <!-- Unavailable Slots Waitlist Callout -->
        <div v-if="unavailableSlots.length" class="waitlist-panel">
          <div class="waitlist-header">
            <span class="material-symbols-outlined text-coral">notification_important</span>
            <div>
              <strong>{{ t('schedule.waitlistCallout') }}</strong>
              <p class="muted" style="margin: 2px 0 0">{{ t('schedule.joinWaitlist') }}</p>
            </div>
          </div>
          <div class="waitlist-chips-row">
            <button
              v-for="slot in unavailableSlots"
              :key="slot.startsAt"
              type="button"
              class="waitlist-slot-chip"
              @click="joinWaitlist(slot)"
            >
              <span class="material-symbols-outlined icon-14">add_alert</span>
              {{ formatTime(slot.startsAt) }}
            </button>
          </div>
        </div>

        <p v-if="error" class="error" style="margin-top: 18px">{{ error }}</p>
        <p v-if="notice" class="notice" style="margin-top: 18px">{{ notice }}</p>
      </section>

      <!-- Right: Sticky Booking Summary -->
      <aside class="summary-sidebar">
        <div class="summary-card">
          <div class="summary-card-head">
            <div class="summary-title-group">
              <span class="material-symbols-outlined icon-22 text-pink">sports_tennis</span>
              <h2>{{ t('schedule.yourSummary') }}</h2>
            </div>
            <span class="slots-count-badge">
              {{ store.selectedSlots.length }} ca
            </span>
          </div>

          <!-- List of Selected Slots -->
          <div v-if="store.selectedSlots.length" class="selected-slots-list">
            <div
              v-for="slot in store.selectedSlots"
              :key="slot.startsAt"
              class="selected-slot-item"
            >
              <div class="slot-item-info">
                <span class="slot-item-court">{{ court?.name || 'Sân Aura' }}</span>
                <span class="slot-item-time">
                  {{ date }} · {{ formatTime(slot.startsAt) }} – {{ formatTime(slot.endsAt) }}
                </span>
              </div>
              <div class="slot-item-right">
                <span class="slot-item-price">{{ formatCurrency(slot.price) }}</span>
                <button
                  type="button"
                  class="btn-remove-slot"
                  @click="store.toggle(slot)"
                >
                  <span class="material-symbols-outlined">close</span>
                </button>
              </div>
            </div>
          </div>

          <div v-else class="no-selection-hint">
            <span class="material-symbols-outlined hint-icon">touch_app</span>
            <p>{{ t('schedule.noSlotsSelected') }}</p>
          </div>

          <!-- Coupon Input -->
          <div v-if="!route.query.reschedule" class="coupon-field-group">
            <label class="coupon-label">{{ t('schedule.applyCoupon') }}</label>
            <div class="coupon-input-wrap">
              <span class="material-symbols-outlined coupon-icon">confirmation_number</span>
              <input
                v-model="couponCode"
                type="text"
                class="coupon-input"
                :placeholder="t('schedule.couponPlaceholder')"
                maxlength="40"
              />
            </div>
          </div>

          <div class="summary-divider"></div>

          <!-- Total & CTA -->
          <div class="summary-total-row">
            <span class="total-label">{{ t('schedule.totalPrice') }}</span>
            <span class="total-amount">{{ formatCurrency(totalPrice) }}</span>
          </div>

          <button
            type="button"
            class="btn-complete-booking"
            :disabled="!store.selectedSlots.length || loading"
            @click="next"
          >
            <span>
              {{ loading ? t('common.submitting') : (route.query.reschedule ? t('schedule.confirmReschedule') : t('schedule.completeBooking')) }}
            </span>
            <span class="material-symbols-outlined">arrow_forward</span>
          </button>
        </div>

        <!-- Venue Feature Highlight Card -->
        <div class="venue-highlight-card">
          <img
            class="highlight-bg-img"
            :src="court?.image_url || '/aura/court-1.jpg'"
            :alt="court?.name"
          />
          <div class="highlight-overlay">
            <h4>{{ t('schedule.facilityHighlight') }}</h4>
            <p>{{ t('schedule.facilityHighlightDesc') }}</p>
          </div>
        </div>
      </aside>
    </div>
  </main>
</template>

<style scoped>
.page-shell {
  max-width: 1280px;
  margin: 0 auto;
  padding: 40px 24px 90px;
}

/* Header & Date Strip */
.schedule-header {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding-bottom: 24px;
  border-bottom: 1px solid var(--border, #e0e4ea);
  margin-bottom: 28px;
}

@media (min-width: 900px) {
  .schedule-header {
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
  }
}

.header-left h1 {
  margin: 6px 0 6px;
}

/* Date Carousel */
.date-carousel-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.date-carousel {
  display: flex;
  gap: 8px;
}

.carousel-date-pill {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-width: 68px;
  height: 72px;
  border-radius: 16px;
  border: 1px solid var(--outline, #d8dce2);
  background: var(--card-bg, #ffffff);
  color: var(--muted, #5d5f5f);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.carousel-date-pill:hover {
  background: var(--surface-subtle, #f5f6f8);
  border-color: var(--pink-dark, #78555e);
}

.carousel-date-pill.active {
  background: var(--pink, #ffd1dc);
  border-color: var(--pink-dark, #78555e);
  color: var(--pink-dark, #78555e);
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.15);
}

:global(html.dark) .carousel-date-pill {
  background: var(--card-bg, #22242a);
  border-color: var(--outline, #383c46);
}

:global(html.dark) .carousel-date-pill.active {
  background: var(--pink-dark, #78555e);
  color: #ffffff;
}

.day-name {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  opacity: 0.75;
}

.day-num {
  font-size: 20px;
  font-weight: 800;
  line-height: 1.2;
}

.custom-date-btn-wrap {
  flex-shrink: 0;
}

/* Reschedule Banner */
.reschedule-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 20px;
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
  border-radius: 16px;
  font-weight: 700;
  margin-bottom: 24px;
}

/* Main Layout */
.schedule-layout {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

@media (min-width: 1024px) {
  .schedule-layout {
    flex-direction: row;
    align-items: flex-start;
  }
}

.slots-section {
  flex: 1;
  min-width: 0;
}

/* Court Select Strip */
.court-select-strip {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 18px;
  padding: 14px 18px;
  margin-bottom: 18px;
  gap: 16px;
  flex-wrap: wrap;
}

:global(html.dark) .court-select-strip {
  background: var(--card-bg, #22242a);
}

.strip-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 240px;
}

.court-dropdown-label {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  white-space: nowrap;
}

.court-dropdown-box {
  flex: 1;
  max-width: 320px;
}

.strip-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  background: var(--surface-subtle, #f5f6f8);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted, #5d5f5f);
}

:global(html.dark) .strip-badge {
  background: var(--surface-subtle, #262930);
}

/* Legend */
.schedule-legend-bar {
  display: flex;
  gap: 20px;
  padding: 12px 16px;
  background: var(--surface-subtle, #f5f6f8);
  border-radius: 12px;
  margin-bottom: 20px;
  font-size: 12px;
  color: var(--muted, #5d5f5f);
}

:global(html.dark) .schedule-legend-bar {
  background: var(--surface-subtle, #262930);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.legend-indicator {
  width: 12px;
  height: 12px;
  border-radius: 4px;
}

.legend-indicator.available {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--outline, #d8dce2);
}

.legend-indicator.selected {
  background: var(--pink, #ffd1dc);
  border: 1px solid var(--pink-dark, #78555e);
}

.legend-indicator.booked {
  background: var(--outline, #d8dce2);
  opacity: 0.6;
}

/* Interactive Slots Grid */
.slots-interactive-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 12px;
}

.interactive-slot-btn {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 14px 12px;
  border-radius: 14px;
  border: 1px solid var(--outline, #d8dce2);
  background: var(--card-bg, #ffffff);
  color: var(--ink, #191c1d);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  min-height: 76px;
}

:global(html.dark) .interactive-slot-btn {
  background: var(--card-bg, #22242a);
  border-color: var(--outline, #383c46);
}

.interactive-slot-btn:hover:not(:disabled) {
  border-color: var(--pink-dark, #78555e);
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(120, 85, 94, 0.1);
}

.interactive-slot-btn.is-selected {
  background: var(--pink, #ffd1dc);
  border-color: var(--pink-dark, #78555e);
  color: var(--pink-dark, #78555e);
  box-shadow: 0 6px 20px rgba(120, 85, 94, 0.2);
  transform: scale(1.02);
}

:global(html.dark) .interactive-slot-btn.is-selected {
  background: var(--pink-dark, #78555e);
  color: #ffffff;
}

.interactive-slot-btn.is-unavailable {
  background: var(--surface-subtle, #f5f6f8);
  opacity: 0.45;
  cursor: not-allowed;
  border-style: dashed;
}

:global(html.dark) .interactive-slot-btn.is-unavailable {
  background: var(--surface-subtle, #262930);
}

.slot-time-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.slot-time {
  font-size: 15px;
  font-weight: 700;
}

.check-icon {
  font-size: 18px;
  color: var(--pink-dark, #78555e);
  font-variation-settings: 'FILL' 1;
}

:global(html.dark) .check-icon {
  color: #ffffff;
}

.lock-icon {
  font-size: 16px;
  color: var(--muted, #5d5f5f);
}

.slot-price-row {
  margin-top: 8px;
  font-size: 12.5px;
  font-weight: 600;
  opacity: 0.85;
}

/* Empty slots */
.empty-slots-box {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 18px;
  padding: 48px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 12px;
  color: var(--muted, #5d5f5f);
}

.empty-icon {
  font-size: 36px;
  color: var(--pink-dark, #78555e);
}

/* Waitlist Panel */
.waitlist-panel {
  margin-top: 24px;
  background: var(--surface-subtle, #f5f6f8);
  border: 1px dashed var(--outline, #d8dce2);
  border-radius: 18px;
  padding: 18px 20px;
}

:global(html.dark) .waitlist-panel {
  background: var(--surface-subtle, #262930);
  border-color: var(--outline, #383c46);
}

.waitlist-header {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 12px;
}

.text-coral {
  color: #ba1a1a;
}

.waitlist-chips-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.waitlist-slot-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid var(--outline, #d8dce2);
  background: var(--card-bg, #ffffff);
  color: var(--ink, #191c1d);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

:global(html.dark) .waitlist-slot-chip {
  background: var(--card-bg, #22242a);
}

.waitlist-slot-chip:hover {
  background: var(--pink-light, #ffd9e2);
  border-color: var(--pink-dark, #78555e);
  color: var(--pink-dark, #78555e);
}

/* Summary Sidebar */
.summary-sidebar {
  width: 100%;
  flex-shrink: 0;
}

@media (min-width: 1024px) {
  .summary-sidebar {
    width: 360px;
    position: sticky;
    top: 96px;
  }
}

.summary-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 24px;
  padding: 24px;
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .summary-card {
  background: var(--card-bg, #22242a);
  border-color: var(--border, #353942);
}

.summary-card-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.summary-title-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.summary-title-group h2 {
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin: 0;
}

.text-pink {
  color: var(--pink-dark, #78555e);
}

.slots-count-badge {
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
  font-size: 11px;
  font-weight: 800;
}

/* Selected slots list */
.selected-slots-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 240px;
  overflow-y: auto;
  margin-bottom: 16px;
}

.selected-slot-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  background: var(--surface-subtle, #f5f6f8);
  border-radius: 12px;
  gap: 10px;
}

:global(html.dark) .selected-slot-item {
  background: var(--surface-subtle, #262930);
}

.slot-item-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.slot-item-court {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

.slot-item-time {
  font-size: 11.5px;
  color: var(--muted, #5d5f5f);
}

.slot-item-right {
  display: flex;
  align-items: center;
  gap: 8px;
}

.slot-item-price {
  font-size: 13.5px;
  font-weight: 800;
  color: var(--pink-dark, #78555e);
}

.btn-remove-slot {
  background: transparent;
  border: none;
  color: var(--muted, #5d5f5f);
  cursor: pointer;
  padding: 2px;
  display: grid;
  place-items: center;
}

.btn-remove-slot:hover {
  color: #ba1a1a;
}

.btn-remove-slot .material-symbols-outlined {
  font-size: 16px;
}

.no-selection-hint {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 8px;
  padding: 24px 12px;
  color: var(--muted, #5d5f5f);
  background: var(--surface-subtle, #f5f6f8);
  border-radius: 14px;
  margin-bottom: 16px;
}

:global(html.dark) .no-selection-hint {
  background: var(--surface-subtle, #262930);
}

.hint-icon {
  font-size: 26px;
  color: var(--pink-dark, #78555e);
}

.no-selection-hint p {
  font-size: 12.5px;
  margin: 0;
}

/* Coupon */
.coupon-field-group {
  margin-top: 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.coupon-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--muted, #5d5f5f);
}

.coupon-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.coupon-icon {
  position: absolute;
  left: 10px;
  font-size: 18px;
  color: var(--muted, #5d5f5f);
}

.coupon-input {
  width: 100%;
  padding: 10px 12px 10px 36px;
  border-radius: 10px;
  border: 1px solid var(--outline, #d8dce2);
  background: var(--surface-subtle, #f5f6f8);
  font-size: 13px;
  color: var(--ink, #191c1d);
  outline: none;
}

.coupon-input:focus {
  border-color: var(--pink-dark, #78555e);
}

/* Divider & Total */
.summary-divider {
  height: 1px;
  background: var(--border, #e0e4ea);
  margin: 18px 0 16px;
}

.summary-total-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 20px;
}

.total-label {
  font-size: 12px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--muted, #5d5f5f);
}

.total-amount {
  font-size: 26px;
  font-weight: 900;
  color: var(--ink, #191c1d);
  letter-spacing: -0.03em;
}

.btn-complete-booking {
  width: 100%;
  padding: 14px;
  border-radius: 999px;
  border: none;
  background: var(--pink-dark, #78555e);
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 800;
  letter-spacing: 0.04em;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 6px 20px rgba(120, 85, 94, 0.25);
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.btn-complete-booking:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 10px 26px rgba(120, 85, 94, 0.35);
}

.btn-complete-booking:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none;
}

/* Venue Highlight Card */
.venue-highlight-card {
  position: relative;
  height: 150px;
  border-radius: 20px;
  overflow: hidden;
  margin-top: 18px;
}

.highlight-bg-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s ease;
}

.venue-highlight-card:hover .highlight-bg-img {
  transform: scale(1.05);
}

.highlight-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.1) 0%, rgba(0, 0, 0, 0.75) 100%);
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 16px;
  color: #ffffff;
}

.highlight-overlay h4 {
  margin: 0 0 4px;
  font-size: 13.5px;
  font-weight: 700;
}

.highlight-overlay p {
  margin: 0;
  font-size: 11px;
  opacity: 0.85;
  line-height: 1.4;
}
</style>
