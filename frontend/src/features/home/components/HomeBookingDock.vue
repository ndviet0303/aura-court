<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { venueClockRange } from '@/core/utils/venueTime'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { useBookingStore } from '@/features/booking/stores/bookingStore'
import BookingAgreement from '@/features/booking/components/BookingAgreement.vue'
import { useSelectionQuote } from '@/features/booking/composables/useSelectionQuote'
import {
  bookingErrorMessage,
  creditOrganizationOf,
  formatDuration,
  validBookerPhone,
} from '@/features/booking/utils/reservation'

// Floating checkout dock for the interval picked on the home page.
const props = defineProps<{ courtId: number | null }>()

const { t } = useI18n()
const store = useBookingStore()
const auth = useAuthStore()
const router = useRouter()
// The server quote total shown here is the expected_total the checkout sends.
const quote = reactive(useSelectionQuote())
const loading = ref(false)
const error = ref('')

const totalText = computed(() => (quote.total === null ? '—' : formatCurrency(quote.total)))
// The interval by its time range and duration, never a count of 30-minute times (design D14).
const picked = computed(() => ({
  range: venueClockRange(store.selection?.startsAt, store.selection?.endsAt),
  duration: formatDuration(store.selection?.minutes ?? 0),
}))
// A signed-in customer books only at a quoted total; a guest is sent to sign in.
const blocked = computed(
  () =>
    !!auth.user &&
    (!validBookerPhone(auth.user.phone) || !!store.priceChange || quote.total === null),
)

// agreedTotal is set only when resubmitting at a new price the customer confirmed.
async function submit(agreedTotal?: number) {
  if (!auth.user) return router.push({ name: 'login', query: { redirect: '/booking' } })
  const expectedTotal = agreedTotal ?? quote.total
  if (!store.selectedSlots.length || expectedTotal === null) return
  loading.value = true
  error.value = ''
  try {
    const reservation = await store.checkoutReservation(expectedTotal)
    router.push({ name: 'reservations', params: { code: reservation.code } })
  } catch (cause: unknown) {
    if (!store.catchPriceChange(cause, submit)) {
      error.value = bookingErrorMessage(cause, t('common.error'))
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Transition name="slide-up">
    <div
      v-if="store.selectedSlots.length"
      class="floating-booking"
      :class="{ 'with-agreement': auth.user && props.courtId }"
      role="region"
      :aria-label="t('home.floating.summary', { ...picked, total: totalText })"
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
          <span>{{ t('home.floating.selection', picked) }}</span>
        </div>
        <div class="dock-total-box">
          <span class="dock-total-lbl">{{ t('home.floating.totalLabel') }}</span>
          <strong class="dock-total-val">{{ totalText }}</strong>
        </div>
      </div>

      <!-- Dock Booking Agreement -->
      <div v-if="auth.user && props.courtId" class="dock-loyalty-container">
        <BookingAgreement
          :rental="quote.rental"
          :payable="quote.total"
          :quote-error="quote.error"
          :price-change="store.priceChange"
          :busy="loading"
          :organization-id="creditOrganizationOf(store.courts.find((c) => c.id === props.courtId))"
          @confirm-price="store.confirmPrice"
          @retry-quote="quote.retry"
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
        <button class="btn btn-floating-proceed" :disabled="loading || blocked" @click="submit()">
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
