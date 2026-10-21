import { computed, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import { i18n } from '@/i18n'
import { bookingApi } from '../api/bookingApi'
import type { Court, AvailabilitySlot } from '@/features/court/types/court'
import { usePriceChange } from '../composables/usePriceChange'

export const useBookingStore = defineStore('booking', () => {
  const courts = ref<Court[]>([])
  const slots = ref<AvailabilitySlot[]>([])
  const selectedCourt = ref<number | null>(null)
  const selectedSlots = ref<AvailabilitySlot[]>([])
  const walletBalance = ref<number>(0)
  const error = ref('')
  const courtsLoading = ref(false)
  const slotsLoading = ref(false)
  const loading = computed(() => courtsLoading.value || slotsLoading.value)

  async function loadCourts(params?: { sport_type?: string; venue_id?: number }) {
    error.value = ''
    courtsLoading.value = true
    try {
      courts.value = await bookingApi.getCourts(params)
    } catch (cause) {
      courts.value = []
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const t = (i18n.global as any).t || ((k: string) => k)
      error.value = t('booking.courtsError') || 'Không thể tải danh sách sân. Vui lòng thử lại.'
      throw cause
    } finally {
      courtsLoading.value = false
    }
  }

  let availabilityRequestId = 0
  let activeAvailabilityContext = ''

  async function loadAvailability(courtId: number, date: string) {
    const currentGeneration = ++availabilityRequestId
    const requestContext = `${courtId}:${date}`
    activeAvailabilityContext = requestContext
    selectedCourt.value = courtId
    selectedSlots.value = []
    error.value = ''
    slotsLoading.value = true

    try {
      const res = await bookingApi.getAvailability(courtId, date)
      // Guard at commit point: ONLY mutate slots if this generation is still active
      if (
        currentGeneration !== availabilityRequestId ||
        activeAvailabilityContext !== requestContext
      ) {
        return []
      }
      slots.value = res
      return res
    } catch (cause) {
      if (
        currentGeneration !== availabilityRequestId ||
        activeAvailabilityContext !== requestContext
      ) {
        return []
      }
      slots.value = []
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const t = (i18n.global as any).t || ((k: string) => k)
      error.value = t('booking.availabilityError') || 'Không thể tải lịch sân. Vui lòng thử lại.'
      throw cause
    } finally {
      if (currentGeneration === availabilityRequestId) {
        slotsLoading.value = false
      }
    }
  }

  function toggle(slot: AvailabilitySlot) {
    if (!slot.available) return
    const index = selectedSlots.value.findIndex((item) => item.startsAt === slot.startsAt)
    if (index >= 0) selectedSlots.value.splice(index, 1)
    else selectedSlots.value.push(slot)
    selectedSlots.value.sort((a, b) => a.startsAt.localeCompare(b.startsAt))
  }

  let inFlightWalletBalance: Promise<number> | null = null

  async function fetchWalletBalance() {
    if (inFlightWalletBalance) return inFlightWalletBalance
    inFlightWalletBalance = (async () => {
      try {
        const res = await bookingApi.getWalletBalance()
        if (typeof res?.balance === 'number') {
          walletBalance.value = res.balance
        }
        return walletBalance.value
      } catch {
        return walletBalance.value
      } finally {
        inFlightWalletBalance = null
      }
    })()
    return inFlightWalletBalance
  }

  // A PRICE_CHANGED awaiting the customer's confirmation; another court or selection drops it.
  const price = usePriceChange()
  watch([selectedCourt, () => selectedSlots.value.map((slot) => slot.startsAt).join()], price.clear)

  // Checks out the selected slots at the amount payable the customer was shown; the server prices them
  // in the same transaction.
  async function checkoutReservation(expectedTotal: number) {
    const { reservation } = await bookingApi.checkoutReservation({
      court_id: selectedCourt.value ?? undefined,
      slots: selectedSlots.value.map((slot) => slot.startsAt),
      expected_total: expectedTotal,
    })
    return reservation
  }

  return {
    courts,
    slots,
    selectedCourt,
    selectedSlots,
    walletBalance,
    error,
    loading,
    courtsLoading,
    slotsLoading,
    loadCourts,
    loadAvailability,
    toggle,
    checkoutReservation,
    priceChange: price.priceChange,
    catchPriceChange: price.caught,
    confirmPrice: price.confirm,
    fetchWalletBalance,
  }
})
