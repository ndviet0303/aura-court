import { computed, ref, watch } from 'vue'
import type { ApiError } from '@/core/types/api'
import { errorMessage } from '@/core/utils/errorMessage'
import { bookingApi } from '../api/bookingApi'
import { useBookingStore } from '../stores/bookingStore'
import type { PriceQuote } from '../types/reservation'

// Promo code for the checkout summary: prices the selected court times with the code so the
// discount shows before checkout. Editing the code or the selection drops the applied coupon.
export function useCheckoutCoupon() {
  const store = useBookingStore()
  const code = ref('')
  const quote = ref<PriceQuote | null>(null)
  const error = ref('')
  const loading = ref(false)
  let request = 0

  const slots = computed(() => store.selectedSlots.map((slot) => slot.startsAt))
  const subtotal = computed(() => store.selectedSlots.reduce((sum, slot) => sum + slot.price, 0))
  const appliedCode = computed(() => quote.value?.benefit_snapshot?.coupon_code ?? null)
  const discount = computed(() => quote.value?.benefit_snapshot?.coupon_discount_amount ?? 0)
  const total = computed(() => quote.value?.total_amount ?? subtotal.value)

  function clear() {
    request++
    quote.value = null
    error.value = ''
    loading.value = false
  }

  async function apply() {
    const couponCode = code.value.trim()
    const courtId = store.selectedCourt
    if (!couponCode || !courtId || !slots.value.length) return
    clear()
    const id = request
    loading.value = true
    try {
      const res = await bookingApi.getPriceQuote({
        court_id: courtId,
        slots: slots.value,
        coupon_code: couponCode,
      })
      if (id === request) quote.value = res.quote
    } catch (cause) {
      if (id !== request) return
      const err = cause as ApiError | undefined
      error.value =
        err?.errors?.coupon_code?.[0] ?? (err?.status === 422 ? err.message : errorMessage(cause))
    } finally {
      if (id === request) loading.value = false
    }
  }

  watch([code, () => store.selectedCourt, () => slots.value.join()], clear)

  return { code, quote, error, loading, appliedCode, discount, total, apply, clear }
}
