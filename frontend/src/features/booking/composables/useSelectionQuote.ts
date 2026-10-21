import { computed, ref, watch } from 'vue'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { bookingApi } from '../api/bookingApi'
import { useBookingStore } from '../stores/bookingStore'
import type { PriceQuote } from '../types/reservation'
import { bookingErrorMessage } from '../utils/reservation'

// Server quote of the selected court times: its total is the amount shown and sent as expected_total.
// Each slot's availability price is rounded on its own, so their sum can be below the quote total.
// While the quote is pending or after it failed there is no total, so booking waits for it.
export function useSelectionQuote() {
  const store = useBookingStore()
  const auth = useAuthStore()
  const quote = ref<PriceQuote | null>(null)
  const error = ref('')
  const loading = ref(false)
  let request = 0

  const slots = computed(() => store.selectedSlots.map((slot) => slot.startsAt))
  const total = computed(() => quote.value?.total_amount ?? null)
  const rental = computed(() => quote.value?.subtotal ?? total.value)

  function clear() {
    request++
    quote.value = null
    error.value = ''
    loading.value = false
  }

  // Quotes need a signed-in customer (POST /price-quotes is behind auth); a guest signs in to book.
  async function load() {
    clear()
    const courtId = store.selectedCourt
    if (!auth.user || !courtId || !slots.value.length) return
    const id = request
    loading.value = true
    try {
      const res = await bookingApi.getPriceQuote({ court_id: courtId, slots: slots.value })
      if (id === request) quote.value = res.quote
    } catch (cause) {
      if (id === request) error.value = bookingErrorMessage(cause)
    } finally {
      if (id === request) loading.value = false
    }
  }

  watch([() => store.selectedCourt, () => slots.value.join(), () => auth.user?.id], load, {
    immediate: true,
  })

  return { total, rental, loading, error, retry: load }
}
