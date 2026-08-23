<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, errorMessage } from '@/lib/api'
import DatePicker from '@/components/DatePicker.vue'
import { useAuthStore } from '@/stores/auth'
import { useBookingStore } from '@/stores/booking'
import { formatCurrency, formatTime } from '@/i18n'
import type { AvailabilitySlot, Court } from '@/types'
import SkeletonLoader from '@/components/SkeletonLoader.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const store = useBookingStore()
const date = ref(new Date(Date.now() + 86400000).toISOString().slice(0, 10))
const couponCode = ref('')
const court = computed(() => store.courts.find((item: Court) => item.id === Number(route.params.id)) || store.courts[0])
const unavailableSlots = computed(() => store.slots.filter((slot: AvailabilitySlot) => !slot.available))
const error = ref('')
const notice = ref('')
const loading = ref(false)

onMounted(async () => {
  try {
    await store.loadCourts()
    if (court.value) await store.loadAvailability(court.value.id, date.value)
  } catch {
    error.value = store.error
  }
})

async function refresh() {
  if (court.value) {
    try {
      await store.loadAvailability(court.value.id, date.value)
    } catch {
      error.value = store.error
    }
  }
}

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
    const payload = { court_id: court.value?.id, slots: store.selectedSlots.map((slot: AvailabilitySlot) => slot.startsAt) }
    const result = route.query.reschedule
      ? await api.post<{ booking: { code: string } }>('/bookings/' + route.query.reschedule + '/reschedule', payload)
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
  <section class="page-shell">
    <div class="eyebrow">{{ t('schedule.eyebrow') }}</div>
    <h1 class="page-title">{{ t('schedule.title') }}</h1>
    <p class="muted">{{ court?.name || 'Aura Court' }} · {{ t('schedule.subtitle') }}</p>
    <div class="panel" style="margin-top:30px">
      <div class="row">
        <div>
          <strong>{{ t('schedule.selectDate') }}</strong>
          <p class="muted" style="margin:5px 0 0">60m · {{ t('courts.priceList') }}</p>
        </div>
        <DatePicker v-model="date" @change="refresh" />
      </div>
      <div v-if="store.slotsLoading || (store.loading && !store.slots.length)" class="slot-grid">
        <SkeletonLoader type="slot" :count="10" />
      </div>
      <div v-else class="slot-grid">
        <button
          v-for="slot in store.slots"
          :key="slot.startsAt"
          class="slot"
          :class="{ selected: store.selectedSlots.some((item: AvailabilitySlot) => item.startsAt === slot.startsAt), unavailable: !slot.available }"
          :disabled="!slot.available"
          @click="store.toggle(slot)"
        >
          {{ formatTime(slot.startsAt) }}<br>
          <small>{{ formatCurrency(slot.price) }}</small>
        </button>
      </div>
      <div v-if="unavailableSlots.length" class="notice" style="margin-top:18px">
        <strong>{{ t('common.status.closed') }}?</strong>
        <span>{{ t('schedule.noSlots') }}</span>
        <div class="actions" style="margin-top:10px">
          <button v-for="slot in unavailableSlots" :key="slot.startsAt" type="button" class="link-button" @click="joinWaitlist(slot)">
            {{ formatTime(slot.startsAt) }}
          </button>
        </div>
      </div>
      <div v-if="!route.query.reschedule" class="field" style="margin-top:22px">
        <label>Coupon / Voucher</label>
        <input v-model="couponCode" maxlength="40" placeholder="AURA10..." />
      </div>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="notice" class="notice">{{ notice }}</p>
      <div class="row" style="margin-top:28px">
        <span class="muted">{{ t('schedule.selectedSlotsCount', { count: store.selectedSlots.length }) }}</span>
        <button class="btn" :disabled="!store.selectedSlots.length || loading" @click="next">
          {{ loading ? t('common.submitting') : t('common.continue') }}
          <span class="material-symbols-outlined">arrow_forward</span>
        </button>
      </div>
    </div>
  </section>
</template>
