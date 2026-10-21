<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { useBookingStore } from '@/features/booking/stores/bookingStore'
import LoyaltyPointSelector from '@/features/booking/components/LoyaltyPointSelector.vue'
import CouponCodeField from '@/features/booking/components/CouponCodeField.vue'
import BookingAgreement from '@/features/booking/components/BookingAgreement.vue'
import { useCheckoutCoupon } from '@/features/booking/composables/useCheckoutCoupon'
import {
  amountPayable,
  bookingErrorMessage,
  validBookerPhone,
  type LoyaltySelection,
} from '@/features/booking/utils/reservation'

// Floating checkout dock for the court times picked on the home page.
const props = defineProps<{ courtId: number | null }>()

const { t } = useI18n()
const store = useBookingStore()
const auth = useAuthStore()
const router = useRouter()
const coupon = reactive(useCheckoutCoupon())
const loyaltyPayload = ref<LoyaltySelection>(null)
const loading = ref(false)
const error = ref('')

// The amount payable shown here is the expected_total the checkout sends.
const payable = computed(() => amountPayable(coupon.total, loyaltyPayload.value))
const blocked = computed(
  () => !!auth.user && (!validBookerPhone(auth.user.phone) || !!store.priceChange),
)

// agreedTotal is set only when resubmitting at a new price the customer confirmed.
async function submit(agreedTotal?: number) {
  if (!auth.user) return router.push({ name: 'login', query: { redirect: '/booking' } })
  if (!store.selectedSlots.length) return
  loading.value = true
  error.value = ''
  try {
    const reservation = await store.checkoutReservation(
      loyaltyPayload.value,
      coupon.appliedCode,
      agreedTotal ?? payable.value,
      agreedTotal !== undefined,
    )
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
      role="region"
      :aria-label="
        t('home.floating.summary', {
          count: store.selectedSlots.length,
          total: formatCurrency(payable),
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
          <strong class="dock-total-val">{{ formatCurrency(payable) }}</strong>
        </div>
      </div>

      <!-- Dock Loyalty Point Selector -->
      <div v-if="auth.user && props.courtId" class="dock-loyalty-container">
        <CouponCodeField
          v-model="coupon.code"
          :loading="coupon.loading"
          :error="coupon.error"
          :discount="coupon.discount"
          @apply="coupon.apply"
        />
        <LoyaltyPointSelector
          :court-id="props.courtId"
          :slots="store.selectedSlots.map((s) => s.startsAt)"
          :coupon-code="coupon.appliedCode ?? undefined"
          @change="loyaltyPayload = $event"
        />
        <BookingAgreement
          :rental="coupon.rental"
          :coupon-code="coupon.appliedCode"
          :payable="payable"
          :price-change="store.priceChange"
          :busy="loading"
          @confirm-price="store.confirmPrice"
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
          :disabled="loading || coupon.loading || blocked"
          @click="submit()"
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
