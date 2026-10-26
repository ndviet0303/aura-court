import { computed, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import { i18n } from '@/i18n'
import { bookingApi } from '../api/bookingApi'
import type { Court, AvailabilitySlot } from '@/features/court/types/court'
import { usePriceChange } from '../composables/usePriceChange'
import { useVoucherChoice } from '../composables/useVoucherChoice'
import { useVenueCreditChoice } from '../composables/useVenueCreditChoice'

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

  // The interval sent to quote and checkout: first selected start to last selected end.
  const selection = computed(() => {
    const first = selectedSlots.value[0]
    const last = selectedSlots.value[selectedSlots.value.length - 1]
    if (!first || !last) return null
    const minutes = (Date.parse(last.endsAt) - Date.parse(first.startsAt)) / 60000
    return { startsAt: first.startsAt, endsAt: last.endsAt, minutes }
  })

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
  watch(
    [selectedCourt, () => `${selection.value?.startsAt}/${selection.value?.endsAt}`],
    price.clear,
  )

  // The voucher the booking form applies; BookingVoucherPicker loads it for the selection (module 3 D10).
  const vouchers = useVoucherChoice()
  // The venue credit the booking spends; BookingVenueCreditPicker sets it for every checkout form.
  const credit = useVenueCreditChoice()

  // Checks out the selected interval at the amount payable the customer was shown; the server prices
  // it in the same transaction.
  async function checkoutReservation(expectedTotal: number) {
    const { reservation } = await bookingApi.checkoutReservation({
      court_id: selectedCourt.value ?? undefined,
      starts_at: selection.value?.startsAt,
      ends_at: selection.value?.endsAt,
      expected_total: expectedTotal,
      ...vouchers.payload(),
      ...credit.payload(),
    })
    return reservation
  }

  return {
    courts,
    slots,
    selectedCourt,
    selectedSlots,
    selection,
    walletBalance,
    error,
    loading,
    courtsLoading,
    slotsLoading,
    loadCourts,
    loadAvailability,
    checkoutReservation,
    priceChange: price.priceChange,
    catchPriceChange: price.caught,
    confirmPrice: price.confirm,
    vouchers: vouchers.vouchers,
    voucher: vouchers.voucher,
    vouchersLoading: vouchers.loading,
    voucherDropped: vouchers.dropped,
    voucherCodeError: vouchers.codeError,
    voucherInvalid: vouchers.invalid,
    loadVouchers: vouchers.load,
    chooseVoucher: vouchers.choose,
    enterVoucherCode: vouchers.enterCode,
    setVoucherLink: vouchers.setLink,
    catchVoucherInvalid: vouchers.caught,
    continueWithoutVoucher: vouchers.continueWithout,
    chooseOtherVoucher: vouchers.chooseOther,
    clearVouchers: vouchers.clear,
    venueCreditAmount: credit.amount,
    loadVenueCredits: credit.load,
    venueCreditBalanceAt: credit.balanceAt,
    venueCreditPayload: credit.payload,
    clearVenueCredit: credit.clear,
    fetchWalletBalance,
  }
})
