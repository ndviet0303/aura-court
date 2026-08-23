<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, apiOrigin, errorMessage } from '@/lib/api'
import { formatCurrency, formatDateTime } from '@/i18n'
import type { Booking } from '@/types'
import SkeletonLoader from '@/components/SkeletonLoader.vue'
import AuraLoading from '@/components/AuraLoading.vue'

const { t } = useI18n()
const bookings = ref<Booking[]>([])
const selectedSlots = ref<Record<number, number[]>>({})
const reviewDrafts = ref<Record<number, { rating: number; body: string }>>({})
const error = ref('')
const notice = ref('')
const loading = ref(true)

const statusLabel = (value: string) => t(`common.status.${value}`, value)

async function load() {
  loading.value = true
  try {
    const result = await api.get<{ data: Booking[] }>('/bookings')
    bookings.value = result.data
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    loading.value = false
  }
}

function toggleSlot(bookingId: number, slotId?: number) {
  if (!slotId) return
  const current = selectedSlots.value[bookingId] || []
  selectedSlots.value[bookingId] = current.includes(slotId) ? current.filter((id) => id !== slotId) : [...current, slotId]
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
    await api.post('/bookings/' + booking.code + '/cancel-slots', { slot_ids: slotIds, reason: 'User cancelled slots' })
    selectedSlots.value[booking.id] = []
    notice.value = t('booking.cancelSuccess')
    await load()
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  }
}

function isCompleted(booking: Booking) {
  return booking.status === 'confirmed' && booking.slots.length > 0 && booking.slots.every((slot) => new Date(slot.ends_at).getTime() < Date.now())
}

function draft(booking: Booking) { return reviewDrafts.value[booking.id] ||= { rating: 5, body: '' } }

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
  <section class="row">
    <div>
      <div class="eyebrow">{{ t('booking.eyebrow') }}</div>
      <h1 class="page-title">{{ t('booking.title') }}</h1>
      <p class="muted">{{ t('booking.subtitle') }}</p>
    </div>
    <RouterLink to="/courts" class="btn">{{ t('booking.bookNew') }}</RouterLink>
  </section>

  <p v-if="error" class="error">{{ error }}</p>
  <p v-if="notice" class="notice">{{ notice }}</p>

  <!-- Loading Skeletons -->
  <div v-if="loading" style="margin-top:20px; display:flex; flex-direction:column; gap:16px;">
    <SkeletonLoader type="card" :count="2" />
  </div>

  <div v-else-if="!bookings.length" class="card" style="margin-top:28px">
    <p>{{ t('booking.empty') }}</p>
  </div>

  <div v-else v-for="booking in bookings" :key="booking.id" class="card" style="margin-top:14px">
    <div class="row">
      <div>
        <h3>{{ booking.court.name }} · {{ booking.code }}</h3>
        <p class="muted">{{ booking.slots.map((slot) => formatDateTime(slot.starts_at)).join(' · ') }}</p>
      </div>
      <span class="badge">{{ statusLabel(booking.status) }}</span>
    </div>

    <div class="slot-list" v-if="booking.status === 'confirmed' && booking.slots.length > 1">
      <label v-for="slot in booking.slots" :key="slot.id">
        <input type="checkbox" :checked="selectedSlots[booking.id]?.includes(slot.id || 0)" @change="toggleSlot(booking.id, slot.id)" />
        {{ formatDateTime(slot.starts_at) }} · {{ formatCurrency(slot.unit_price) }}
      </label>
    </div>

    <div class="row">
      <strong>{{ formatCurrency(booking.total_amount) }}</strong>
      <div class="actions">
        <RouterLink v-if="booking.status === 'pending_payment'" :to="'/payment/' + booking.code" class="link-button">
          {{ t('booking.payNow') }}
        </RouterLink>
        <a v-if="booking.status === 'confirmed'" class="link-button" :href="`${apiOrigin}/api/v1/bookings/${booking.code}/calendar.ics`">
          {{ t('booking.addToCalendar') }}
        </a>
        <RouterLink v-if="booking.status === 'confirmed'" :to="'/courts/' + booking.court.id + '/schedule?reschedule=' + booking.code" class="link-button">
          {{ t('booking.reschedule') }}
        </RouterLink>
        <button v-if="booking.status === 'confirmed' && (selectedSlots[booking.id]?.length || 0)" class="link-button danger" @click="cancelSelected(booking)">
          {{ t('booking.cancelSelectedSlots') }}
        </button>
        <button v-if="['pending_payment', 'confirmed'].includes(booking.status)" class="btn" @click="cancel(booking)">
          {{ t('booking.cancelBooking') }}
        </button>
      </div>
    </div>

    <form v-if="isCompleted(booking) && !booking.review" class="panel" style="margin-top:18px" @submit.prevent="submitReview(booking)">
      <strong>{{ t('booking.rateTitle') }}</strong>
      <div class="field">
        <label>{{ t('booking.ratingScore') }}</label>
        <select v-model.number="draft(booking).rating">
          <option v-for="rating in [5, 4, 3, 2, 1]" :key="rating" :value="rating">{{ rating }}/5</option>
        </select>
      </div>
      <div class="field">
        <label>{{ t('booking.reviewComment') }}</label>
        <textarea v-model="draft(booking).body" rows="3" maxlength="2000" />
      </div>
      <button class="btn">{{ t('booking.submitReview') }}</button>
    </form>
    <p v-else-if="booking.review" class="muted" style="margin-top:18px">
      {{ t('booking.reviewedBadge', { rating: booking.review.rating }) }}
    </p>
  </div>
</template>
