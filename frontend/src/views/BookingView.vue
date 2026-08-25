<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, apiOrigin, errorMessage } from '@/lib/api'
import { formatCurrency, formatDateTime } from '@/i18n'
import type { Booking } from '@/types'
import { useAuthStore } from '@/stores/auth'
import SkeletonLoader from '@/components/SkeletonLoader.vue'

const { t } = useI18n()
const auth = useAuthStore()

// State
const bookings = ref<Booking[]>([])
const selectedSlots = ref<Record<number, number[]>>({})
const reviewDrafts = ref<Record<number, { rating: number; body: string }>>({})
const activeTab = ref<'all' | 'upcoming' | 'completed' | 'cancelled'>('all')
const error = ref('')
const notice = ref('')
const loading = ref(true)

const statusLabel = (value: string) => t(`common.status.${value}`, value)

const userInitials = computed(() => {
  if (!auth.user?.name) return 'AC'
  const parts = auth.user.name.trim().split(/\s+/)
  if (parts.length === 1) return (parts[0]?.substring(0, 2) ?? 'AC').toUpperCase()
  return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase()
})

const defaultAvatarUrl = computed(() => {
  const id = auth.user?.id || 1
  return id % 2 === 0 ? '/avatars/athlete-girl.jpg' : '/avatars/athlete-boy.jpg'
})

async function load() {
  loading.value = true
  try {
    const result = await api.get<{ data: Booking[] }>('/bookings')
    bookings.value = result.data || []
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}

function isCompleted(booking: Booking): boolean {
  return (
    booking.status === 'confirmed' &&
    booking.slots.length > 0 &&
    booking.slots.every((slot) => new Date(slot.ends_at).getTime() < Date.now())
  )
}

function isUpcoming(booking: Booking): boolean {
  if (['pending_payment'].includes(booking.status)) return true
  if (booking.status === 'confirmed') {
    return !isCompleted(booking)
  }
  return false
}

// Filtered Bookings by Tab
const filteredBookings = computed(() => {
  if (activeTab.value === 'upcoming') {
    return bookings.value.filter(isUpcoming)
  }
  if (activeTab.value === 'completed') {
    return bookings.value.filter(isCompleted)
  }
  if (activeTab.value === 'cancelled') {
    return bookings.value.filter((b) => b.status === 'cancelled')
  }
  return bookings.value
})

const upcomingCount = computed(() => bookings.value.filter(isUpcoming).length)
const completedCount = computed(() => bookings.value.filter(isCompleted).length)
const cancelledCount = computed(() => bookings.value.filter((b) => b.status === 'cancelled').length)

function toggleSlot(bookingId: number, slotId?: number) {
  if (!slotId) return
  const current = selectedSlots.value[bookingId] || []
  selectedSlots.value[bookingId] = current.includes(slotId)
    ? current.filter((id) => id !== slotId)
    : [...current, slotId]
}

async function cancel(booking: Booking) {
  if (!confirm(t('booking.confirmCancel'))) return
  try {
    await api.post('/bookings/' + booking.code + '/cancel', { reason: 'User cancelled' })
    notice.value = t('booking.cancelSuccess')
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  }
}

async function cancelSelected(booking: Booking) {
  const slotIds = selectedSlots.value[booking.id] || []
  if (!slotIds.length || !confirm(t('booking.confirmCancelSlots'))) return
  try {
    await api.post('/bookings/' + booking.code + '/cancel-slots', {
      slot_ids: slotIds,
      reason: 'User cancelled slots',
    })
    selectedSlots.value[booking.id] = []
    notice.value = t('booking.cancelSuccess')
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  }
}

function draft(booking: Booking) {
  return (reviewDrafts.value[booking.id] ||= { rating: 5, body: '' })
}

async function submitReview(booking: Booking) {
  try {
    await api.post('/reviews', { booking_code: booking.code, ...draft(booking) })
    notice.value = t('booking.reviewThanks')
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  }
}

onMounted(load)
</script>

<template>
  <main class="page-shell">
    <!-- Top Header -->
    <header class="bookings-page-header">
      <div class="header-content">
        <div class="eyebrow">{{ t('booking.eyebrow') }}</div>
        <h1 class="page-title">{{ t('booking.title') }}</h1>
        <p class="muted">{{ t('booking.subtitle') }}</p>
      </div>
      <div style="display: flex; gap: 10px; align-items: center;">
        <RouterLink to="/recurring-booking" class="btn" style="background: var(--pink-light); color: var(--pink-dark); font-weight: 700; border: 1px solid var(--pink);">
          <span class="material-symbols-outlined icon-18">event_repeat</span>
          <span>Đặt sân định kỳ</span>
        </RouterLink>
        <RouterLink to="/courts" class="btn btn-new-booking">
          <span class="material-symbols-outlined icon-18">add</span>
          <span>{{ t('booking.bookNew') }}</span>
        </RouterLink>
      </div>
    </header>

    <p v-if="error" class="error" style="margin-bottom: 20px">{{ error }}</p>
    <p v-if="notice" class="notice" style="margin-bottom: 20px">{{ notice }}</p>

    <!-- Main 2-Column Grid Layout -->
    <div class="bookings-layout">
      <!-- Left Sidebar: Profile & Quick Links -->
      <aside class="bookings-sidebar">
        <!-- User Info Card -->
        <div class="sidebar-user-card">
          <div class="avatar-wrap">
            <img :src="defaultAvatarUrl" :alt="auth.user?.name" class="user-avatar-img" />
            <div class="avatar-badge">{{ userInitials }}</div>
          </div>
          <h2 class="user-name">{{ auth.user?.name || 'Aura Member' }}</h2>
          <p class="user-meta muted">{{ auth.user?.email }}</p>

          <div class="sidebar-nav-list">
            <div class="sidebar-nav-item active">
              <span class="material-symbols-outlined icon-18">calendar_today</span>
              <span>{{ t('booking.title') }}</span>
              <span class="nav-count">{{ bookings.length }}</span>
            </div>
            <RouterLink to="/recurring-booking" class="sidebar-nav-item">
              <span class="material-symbols-outlined icon-18">event_repeat</span>
              <span>Đặt sân định kỳ</span>
              <span class="nav-count" style="background: var(--pink-light); color: var(--pink-dark); font-weight: 700;">PRO</span>
            </RouterLink>
            <RouterLink to="/courts" class="sidebar-nav-item">
              <span class="material-symbols-outlined icon-18">sports_tennis</span>
              <span>{{ t('nav.courts') }}</span>
            </RouterLink>
            <RouterLink to="/profile" class="sidebar-nav-item">
              <span class="material-symbols-outlined icon-18">person</span>
              <span>{{ t('nav.profile') }}</span>
            </RouterLink>
          </div>
        </div>

        <!-- Aura Club Loyalty Status Card -->
        <div class="loyalty-card">
          <div class="loyalty-glow"></div>
          <div class="loyalty-header">
            <span class="loyalty-eyebrow">Aura Club Status</span>
            <span class="material-symbols-outlined icon-18">workspace_premium</span>
          </div>
          <div class="loyalty-points">
            <strong>{{ bookings.length * 150 + 200 }}</strong>
            <small>pts</small>
          </div>
          <div class="loyalty-progress-bar">
            <div
              class="loyalty-progress-fill"
              :style="{ width: Math.min(100, (bookings.length + 1) * 20) + '%' }"
            ></div>
          </div>
          <p class="loyalty-subtext">
            {{ completedCount }} trận đã thi đấu · Tích điểm ưu đãi
          </p>
        </div>
      </aside>

      <!-- Right Main Content: Tabs & Booking Cards -->
      <section class="bookings-content">
        <!-- Status Tabs Bar -->
        <div class="bookings-tabs-bar">
          <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'all' }"
            @click="activeTab = 'all'"
          >
            <span>Tất cả</span>
            <span class="tab-badge">{{ bookings.length }}</span>
          </button>
          <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'upcoming' }"
            @click="activeTab = 'upcoming'"
          >
            <span>Sắp tới</span>
            <span v-if="upcomingCount > 0" class="tab-badge active-tag">{{ upcomingCount }}</span>
          </button>
          <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'completed' }"
            @click="activeTab = 'completed'"
          >
            <span>Đã hoàn thành</span>
            <span v-if="completedCount > 0" class="tab-badge">{{ completedCount }}</span>
          </button>
          <button
            type="button"
            class="tab-btn"
            :class="{ active: activeTab === 'cancelled' }"
            @click="activeTab = 'cancelled'"
          >
            <span>Đã hủy</span>
            <span v-if="cancelledCount > 0" class="tab-badge">{{ cancelledCount }}</span>
          </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="bookings-list-wrap">
          <SkeletonLoader type="card" :count="3" />
        </div>

        <!-- Empty State -->
        <div v-else-if="!filteredBookings.length" class="empty-bookings-card">
          <div class="empty-icon-circle">
            <span class="material-symbols-outlined icon-32">event_available</span>
          </div>
          <h3>{{ t('booking.empty') }}</h3>
          <p class="muted" style="max-width: 420px">
            Khám phá các sân cầu lông tiêu chuẩn BWF và đặt lịch thi đấu ngay hôm nay.
          </p>
          <RouterLink to="/courts" class="btn" style="margin-top: 10px">
            <span class="material-symbols-outlined icon-18">add</span>
            {{ t('booking.bookNew') }}
          </RouterLink>
        </div>

        <!-- Bookings List -->
        <div v-else class="bookings-list-wrap">
          <article
            v-for="booking in filteredBookings"
            :key="booking.id"
            class="booking-item-card"
          >
            <!-- Card Top Header -->
            <div class="booking-card-top">
              <div class="court-title-group">
                <div class="court-badge-icon">
                  <span class="material-symbols-outlined">sports_tennis</span>
                </div>
                <div>
                  <h3 class="court-name">
                    {{ booking.court?.name || 'Sân Aura' }}
                    <span class="booking-code">#{{ booking.code }}</span>
                  </h3>
                  <p class="court-venue-sub muted">
                    {{ booking.court?.venue?.name || t('courts.defaultLocation') }}
                  </p>
                </div>
              </div>

              <!-- Status Badge -->
              <span
                class="status-pill"
                :class="[`status-${booking.status}`, { 'status-completed': isCompleted(booking) }]"
              >
                {{ isCompleted(booking) ? 'Đã hoàn thành' : statusLabel(booking.status) }}
              </span>
            </div>

            <div class="booking-card-divider"></div>

            <!-- Slots and Schedule Info -->
            <div class="booking-slots-info">
              <div class="info-row">
                <span class="material-symbols-outlined icon-18 muted">calendar_month</span>
                <div class="slots-tags-list">
                  <span
                    v-for="slot in booking.slots"
                    :key="slot.id || slot.starts_at"
                    class="slot-time-pill"
                  >
                    {{ formatDateTime(slot.starts_at) }}
                    <small class="slot-price-tag">({{ formatCurrency(slot.unit_price) }})</small>
                  </span>
                </div>
              </div>

              <!-- Multi-slot cancel checkboxes if confirmed -->
              <div
                v-if="booking.status === 'confirmed' && booking.slots.length > 1 && !isCompleted(booking)"
                class="cancel-slots-select-wrap"
              >
                <span class="cancel-slots-label">Chọn từng ca muốn hủy:</span>
                <div class="slots-checkbox-grid">
                  <label
                    v-for="slot in booking.slots"
                    :key="slot.id"
                    class="slot-checkbox-label"
                  >
                    <input
                      type="checkbox"
                      :checked="selectedSlots[booking.id]?.includes(slot.id || 0)"
                      @change="toggleSlot(booking.id, slot.id)"
                    />
                    <span>{{ formatDateTime(slot.starts_at) }}</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Card Bottom Bar: Price & Actions -->
            <div class="booking-card-bottom">
              <div class="price-summary-box">
                <span class="price-label">Tổng thanh toán</span>
                <strong class="total-amount">{{ formatCurrency(booking.total_amount) }}</strong>
              </div>

              <div class="booking-action-buttons">
                <!-- Pay now -->
                <RouterLink
                  v-if="booking.status === 'pending_payment'"
                  :to="'/payment/' + booking.code"
                  class="btn btn-pay-now"
                >
                  <span class="material-symbols-outlined icon-18">credit_card</span>
                  {{ t('booking.payNow') }}
                </RouterLink>

                <!-- Add to calendar -->
                <a
                  v-if="booking.status === 'confirmed'"
                  class="btn secondary sm"
                  :href="`${apiOrigin}/api/v1/bookings/${booking.code}/calendar.ics`"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <span class="material-symbols-outlined icon-18">event</span>
                  {{ t('booking.addToCalendar') }}
                </a>

                <!-- Reschedule -->
                <RouterLink
                  v-if="booking.status === 'confirmed' && !isCompleted(booking)"
                  :to="'/courts/' + (booking.court.slug || booking.court.id) + '/schedule?reschedule=' + booking.code"
                  class="btn secondary sm"
                >
                  <span class="material-symbols-outlined icon-18">update</span>
                  {{ t('booking.reschedule') }}
                </RouterLink>

                <!-- Cancel selected slots -->
                <button
                  v-if="booking.status === 'confirmed' && (selectedSlots[booking.id]?.length || 0)"
                  type="button"
                  class="btn secondary sm danger"
                  @click="cancelSelected(booking)"
                >
                  {{ t('booking.cancelSelectedSlots') }}
                </button>

                <!-- Cancel entire booking -->
                <button
                  v-if="['pending_payment', 'confirmed'].includes(booking.status) && !isCompleted(booking)"
                  type="button"
                  class="btn secondary sm danger"
                  @click="cancel(booking)"
                >
                  {{ t('booking.cancelBooking') }}
                </button>
              </div>
            </div>

            <!-- Review Form for Completed Bookings -->
            <div v-if="isCompleted(booking)" class="review-container">
              <form
                v-if="!booking.review"
                class="review-form-box"
                @submit.prevent="submitReview(booking)"
              >
                <div class="review-head">
                  <span class="material-symbols-outlined icon-20 text-yellow">star</span>
                  <strong>{{ t('booking.rateTitle') }}</strong>
                </div>
                <div class="review-inputs-row">
                  <div class="field" style="width: 130px">
                    <select v-model.number="draft(booking).rating" class="review-select">
                      <option v-for="r in [5, 4, 3, 2, 1]" :key="r" :value="r">
                        {{ r }} ★ / 5
                      </option>
                    </select>
                  </div>
                  <div class="field" style="flex: 1">
                    <input
                      v-model="draft(booking).body"
                      type="text"
                      class="review-input"
                      placeholder="Chia sẻ cảm nhận về chất lượng sân, đèn chiếu sáng..."
                      maxlength="2000"
                    />
                  </div>
                  <button type="submit" class="btn sm">
                    {{ t('booking.submitReview') }}
                  </button>
                </div>
              </form>

              <!-- Reviewed badge -->
              <div v-else class="reviewed-badge-box">
                <div class="stars-row">
                  <span
                    v-for="s in 5"
                    :key="s"
                    class="material-symbols-outlined star-icon"
                    :class="{ filled: s <= (booking.review?.rating || 5) }"
                  >
                    star
                  </span>
                </div>
                <span class="reviewed-text">
                  {{ t('booking.reviewedBadge', { rating: booking.review.rating }) }}
                </span>
                <span v-if="booking.review.body" class="review-body-text">
                  "{{ booking.review.body }}"
                </span>
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
  padding: 40px 24px 90px;
}

/* Page Header */
.bookings-page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 16px;
  padding-bottom: 24px;
  border-bottom: 1px solid var(--border, #e0e4ea);
  margin-bottom: 32px;
  flex-wrap: wrap;
}

.header-content h1 {
  margin: 6px 0 6px;
}

.btn-new-booking {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

/* Main Layout */
.bookings-layout {
  display: flex;
  flex-direction: column;
  gap: 32px;
}

@media (min-width: 1024px) {
  .bookings-layout {
    display: grid;
    grid-template-columns: 290px 1fr;
    align-items: flex-start;
  }
}

/* Left Sidebar */
.bookings-sidebar {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.sidebar-user-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 24px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
}

:global(html.dark) .sidebar-user-card {
  background: var(--card-bg, #22242a);
  border-color: var(--border, #353942);
}

.avatar-wrap {
  position: relative;
  width: 88px;
  height: 88px;
  margin-bottom: 14px;
}

.user-avatar-img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--pink, #ffd1dc);
}

.avatar-badge {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--pink-dark, #78555e);
  color: #ffffff;
  font-size: 11px;
  font-weight: 800;
  display: grid;
  place-items: center;
  border: 2px solid var(--card-bg, #ffffff);
}

.user-name {
  font-size: 18px;
  font-weight: 800;
  margin: 0 0 4px;
  letter-spacing: -0.02em;
}

.user-meta {
  font-size: 12.5px;
  margin: 0 0 20px;
}

.sidebar-nav-list {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.sidebar-nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  text-decoration: none;
  transition: all 0.2s;
}

.sidebar-nav-item:hover {
  background: var(--surface-subtle, #f5f6f8);
  color: var(--ink, #191c1d);
}

.sidebar-nav-item.active {
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
  font-weight: 700;
}

:global(html.dark) .sidebar-nav-item.active {
  background: var(--pink-dark, #78555e);
  color: #ffffff;
}

.nav-count {
  margin-left: auto;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--surface, #ffffff);
  font-size: 11px;
  font-weight: 700;
}

/* Loyalty Card */
.loyalty-card {
  position: relative;
  background: linear-gradient(135deg, #78555e 0%, #5e3e47 100%);
  color: #ffffff;
  border-radius: 20px;
  padding: 20px;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(120, 85, 94, 0.25);
}

.loyalty-glow {
  position: absolute;
  top: -20px;
  right: -20px;
  width: 100px;
  height: 100px;
  background: rgba(255, 209, 220, 0.25);
  border-radius: 50%;
  filter: blur(20px);
}

.loyalty-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  opacity: 0.9;
}

.loyalty-points {
  display: flex;
  align-items: baseline;
  gap: 4px;
  margin: 12px 0 8px;
}

.loyalty-points strong {
  font-size: 32px;
  font-weight: 900;
  line-height: 1;
}

.loyalty-points small {
  font-size: 13px;
  opacity: 0.85;
}

.loyalty-progress-bar {
  width: 100%;
  height: 5px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 999px;
  overflow: hidden;
  margin-bottom: 8px;
}

.loyalty-progress-fill {
  height: 100%;
  background: #ffd1dc;
  border-radius: 999px;
}

.loyalty-subtext {
  font-size: 11.5px;
  opacity: 0.85;
  margin: 0;
}

/* Right Content: Tabs */
.bookings-content {
  flex: 1;
  min-width: 0;
}

.bookings-tabs-bar {
  display: flex;
  gap: 10px;
  border-bottom: 1px solid var(--border, #e0e4ea);
  padding-bottom: 12px;
  margin-bottom: 24px;
  overflow-x: auto;
}

.tab-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 999px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--muted, #5d5f5f);
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.tab-btn:hover {
  color: var(--ink, #191c1d);
  background: var(--surface-subtle, #f5f6f8);
}

.tab-btn.active {
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
  border-color: var(--pink, #ffd1dc);
}

:global(html.dark) .tab-btn.active {
  background: var(--pink-dark, #78555e);
  color: #ffffff;
}

.tab-badge {
  font-size: 11px;
  padding: 2px 7px;
  border-radius: 999px;
  background: var(--surface-subtle, #e5e8ec);
  color: var(--ink, #191c1d);
}

.tab-badge.active-tag {
  background: var(--pink-dark, #78555e);
  color: #ffffff;
}

/* Bookings List */
.bookings-list-wrap {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.booking-item-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 20px;
  padding: 22px;
  box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
  transition: transform 0.2s, box-shadow 0.2s;
}

:global(html.dark) .booking-item-card {
  background: var(--card-bg, #22242a);
  border-color: var(--border, #353942);
}

.booking-item-card:hover {
  box-shadow: 0 8px 26px rgba(0, 0, 0, 0.06);
}

.booking-card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  flex-wrap: wrap;
}

.court-title-group {
  display: flex;
  align-items: center;
  gap: 14px;
}

.court-badge-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: var(--surface-subtle, #f5f6f8);
  display: grid;
  place-items: center;
  color: var(--pink-dark, #78555e);
}

.court-name {
  font-size: 18px;
  font-weight: 800;
  margin: 0;
  letter-spacing: -0.02em;
}

.booking-code {
  font-size: 13px;
  font-weight: 600;
  color: var(--muted, #5d5f5f);
  margin-left: 6px;
}

.court-venue-sub {
  font-size: 12.5px;
  margin: 2px 0 0;
}

/* Status Pill */
.status-pill {
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.status-confirmed {
  background: #dcfce7;
  color: #15803d;
}

.status-pending_payment {
  background: #fef3c7;
  color: #b45309;
}

.status-cancelled {
  background: #fee2e2;
  color: #b91c1c;
}

.status-completed {
  background: var(--pink-light, #ffd9e2);
  color: var(--pink-dark, #78555e);
}

:global(html.dark) .status-confirmed {
  background: #14532d;
  color: #86efac;
}

:global(html.dark) .status-pending_payment {
  background: #78350f;
  color: #fde68a;
}

:global(html.dark) .status-cancelled {
  background: #7f1d1d;
  color: #fca5a5;
}

.booking-card-divider {
  height: 1px;
  background: var(--border, #e0e4ea);
  margin: 16px 0;
}

/* Slots info */
.booking-slots-info {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.slots-tags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.slot-time-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: var(--surface-subtle, #f5f6f8);
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 600;
}

:global(html.dark) .slot-time-pill {
  background: var(--surface-subtle, #262930);
}

.slot-price-tag {
  color: var(--pink-dark, #78555e);
  font-weight: 700;
}

/* Cancel multi-slot */
.cancel-slots-select-wrap {
  margin-top: 6px;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--surface-subtle, #f5f6f8);
  font-size: 12px;
}

:global(html.dark) .cancel-slots-select-wrap {
  background: var(--surface-subtle, #262930);
}

.cancel-slots-label {
  font-weight: 700;
  color: var(--muted, #5d5f5f);
  margin-bottom: 6px;
  display: block;
}

.slots-checkbox-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.slot-checkbox-label {
  display: flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
}

/* Bottom Bar */
.booking-card-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 18px;
  padding-top: 16px;
  border-top: 1px dashed var(--border, #e0e4ea);
  gap: 16px;
  flex-wrap: wrap;
}

.price-summary-box {
  display: flex;
  flex-direction: column;
}

.price-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--muted, #5d5f5f);
}

.total-amount {
  font-size: 20px;
  font-weight: 900;
  color: var(--ink, #191c1d);
}

.booking-action-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-pay-now {
  background: var(--pink-dark, #78555e);
  color: #ffffff;
}

.btn.sm {
  padding: 8px 14px;
  font-size: 12px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
}

.btn.danger {
  color: #ba1a1a;
  border-color: #ffdad6;
}

.btn.danger:hover {
  background: #ffdad6;
  color: #ba1a1a;
}

/* Review Box */
.review-container {
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid var(--border, #e0e4ea);
}

.review-form-box {
  background: var(--surface-subtle, #f5f6f8);
  border-radius: 12px;
  padding: 14px;
}

:global(html.dark) .review-form-box {
  background: var(--surface-subtle, #262930);
}

.review-head {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  margin-bottom: 10px;
}

.text-yellow {
  color: #f59e0b;
}

.review-inputs-row {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.review-select,
.review-input {
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline, #d8dce2);
  background: var(--card-bg, #ffffff);
  color: var(--ink, #191c1d);
  font-size: 13px;
  outline: none;
}

:global(html.dark) .review-select,
:global(html.dark) .review-input {
  background: var(--card-bg, #22242a);
}

.review-input {
  width: 100%;
}

.reviewed-badge-box {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}

.stars-row {
  display: flex;
  color: #f59e0b;
}

.star-icon {
  font-size: 16px;
}

.star-icon.filled {
  font-variation-settings: 'FILL' 1;
}

.reviewed-text {
  font-weight: 700;
}

.review-body-text {
  font-style: italic;
  color: var(--muted, #5d5f5f);
}

/* Empty Card */
.empty-bookings-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 24px;
  padding: 60px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 12px;
}

:global(html.dark) .empty-bookings-card {
  background: var(--card-bg, #22242a);
  border-color: var(--border, #353942);
}

.empty-icon-circle {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--pink-light, #ffd9e2);
  display: grid;
  place-items: center;
  color: var(--pink-dark, #78555e);
}
</style>
